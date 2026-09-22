<?php

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Filament\App\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\App\Resources\Invoices\Pages\EditInvoice;
use App\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

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
        ->assertActionVisible('cancelPayment');
});

it('keeps the form editable while the invoice is unpaid', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->sent()->create();
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertFormFieldEnabled('client_id')
        ->assertActionExists(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'));
});

it('downloads the invoice as a PDF', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $invoice = Invoice::factory()->for($book)->create(['invoice_number' => 'INV-2026-0042']);
    InvoiceItem::factory()->for($invoice)->create();

    Livewire::test(ListInvoices::class)
        ->callAction(TestAction::make('downloadPdf')->table($invoice))
        ->assertFileDownloaded('INV-2026-0042.pdf');
});
