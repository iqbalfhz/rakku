<?php

use App\Actions\SendInvoiceByEmail;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Filament\App\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\App\Resources\Invoices\Pages\EditInvoice;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Mail\InvoiceMail;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

it('creates an invoice with its items and a generated number', function () {
    $this->travelTo('2026-09-01');
    $undoRepeaterFake = Repeater::fake();
    $book = actingInBook(User::factory()->premium()->create());
    $client = Client::factory()->for($book)->create();

    Livewire::test(CreateInvoice::class)
        ->assertSchemaStateSet(['invoice_number' => 'INV-2026-0001'])
        ->fillForm([
            'client_id' => $client->id,
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'items' => [
                ['description' => 'Fotocopy A4', 'quantity' => 100, 'unit_price' => 300],
                ['description' => 'Jilid', 'quantity' => 2, 'unit_price' => 5_000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    expect($book->invoices()->sole())
        ->invoice_number->toBe('INV-2026-0001')
        ->status->toBe(InvoiceStatus::Draft)
        ->totalAmount()->toBe(40_000.0);
});

it('marks a sent invoice as paid into the chosen account', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->sent()->create();
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => 90_000]);
    $account = Account::factory()->for($book)->create(['initial_balance' => 0]);
    $category = $book->categories()->ofType(TransactionType::Income)->first();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('markAsPaid')->table($invoice), data: [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'paid_at' => '2026-09-20',
        ])
        ->assertHasNoFormErrors();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Paid)
        ->transaction->category_id->toBe($category->id)
        ->and($account->fresh()->current_balance)->toBe('90000.00');
});

it('rejects paying an invoice into an account from another book', function () {
    $foreignAccount = Account::factory()->create();
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->sent()->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('markAsPaid')->table($invoice), data: [
            'account_id' => $foreignAccount->id,
            'paid_at' => '2026-09-20',
        ])
        ->assertHasFormErrors(['account_id']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('only offers the payment action for sent or overdue invoices', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $draft = Invoice::factory()->for($book)->create();
    $overdue = Invoice::factory()->for($book)->overdue()->create();

    Livewire::test(ListInvoices::class)
        ->assertActionHidden(TestAction::make('markAsPaid')->table($draft))
        ->assertActionVisible(TestAction::make('markAsPaid')->table($overdue));
});

it('locks the form and hides saving once the invoice is paid', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create(['status' => InvoiceStatus::Paid]);
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertFormFieldDisabled('client_id')
        ->assertActionDoesNotExist(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))
        ->assertActionVisible('cancelPayment')
        ->assertActionHidden('sendViaWhatsApp');
});

it('keeps the form editable while the invoice is unpaid', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->sent()->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertFormFieldEnabled('client_id')
        ->assertActionVisible('sendViaWhatsApp')
        ->assertActionVisible('sendViaEmail')
        ->assertActionExists(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'));
});

it('sends a draft invoice via WhatsApp to a new number and saves it to the client', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $client = Client::factory()->for($book)->create(['phone' => '081100000000']);
    $invoice = Invoice::factory()->for($book)->for($client)->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('sendViaWhatsApp')->table($invoice), data: [
            'phone' => '0812 3456 7890',
            'save_to_client' => true,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('WhatsApp dibuka di tab baru');

    expect($client->fresh()->phone)->toBe('0812 3456 7890')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('resends an overdue invoice via WhatsApp without changing its status or the saved number', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $client = Client::factory()->for($book)->create(['phone' => '081100000000']);
    $invoice = Invoice::factory()->for($book)->for($client)->overdue()->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->assertActionHasLabel(TestAction::make('sendViaWhatsApp')->table($invoice), 'Kirim ulang via WhatsApp')
        ->callAction(TestAction::make('sendViaWhatsApp')->table($invoice), data: [
            'phone' => '0899 9999 9999',
            'save_to_client' => false,
        ])
        ->assertHasNoFormErrors();

    expect($client->fresh()->phone)->toBe('081100000000')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('rejects an invalid WhatsApp number', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('sendViaWhatsApp')->table($invoice), data: ['phone' => 'bukan-nomor'])
        ->assertHasFormErrors(['phone' => 'Nomor WhatsApp tidak valid.']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('emails the invoice PDF to the client and marks the draft as sent', function () {
    Mail::fake();
    $book = actingInBook(User::factory()->premium()->create());
    $client = Client::factory()->for($book)->create(['email' => 'lama@klien.test']);
    $invoice = Invoice::factory()->for($book)->for($client)->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('sendViaEmail')->table($invoice), data: [
            'email' => 'baru@klien.test',
            'save_to_client' => true,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified("Invoice {$invoice->invoice_number} terkirim ke baru@klien.test");

    Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->hasTo('baru@klien.test') && $mail->invoice->is($invoice));
    expect($client->fresh()->email)->toBe('baru@klien.test')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('keeps the invoice unchanged and reports the error when the email fails', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create();
    $this->mock(SendInvoiceByEmail::class)
        ->shouldReceive('handle')
        ->andThrow(new TransportException('SMTP tidak tersedia'));

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('sendViaEmail')->table($invoice), data: [
            'email' => 'klien@klien.test',
            'save_to_client' => false,
        ])
        ->assertNotified('Email gagal dikirim');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

it('hides the send actions once the invoice is paid', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create(['status' => InvoiceStatus::Paid]);

    Livewire::test(ListInvoices::class)
        ->assertActionHidden(TestAction::make('sendViaWhatsApp')->table($invoice))
        ->assertActionHidden(TestAction::make('sendViaEmail')->table($invoice));
});

it('downloads the invoice as a PDF', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create(['invoice_number' => 'INV-2026-0042']);
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('downloadPdf')->table($invoice))
        ->assertFileDownloaded('INV-2026-0042.pdf');
});
