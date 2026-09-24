<?php

use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Transaction;
use App\Services\PlanGate;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => null]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Client::query()->create(['public_id' => '01m3klien', 'name' => 'Toko Sinar']);

    $this->travelTo('2026-10-15');
});

/**
 * Invoice yang sudah tersinkron dari server, lengkap dengan satu baris isi.
 */
function syncedInvoice(array $attributes = []): Invoice
{
    $invoice = Invoice::query()->create([
        'public_id' => '01m3invoice',
        'client_public_id' => '01m3klien',
        'invoice_number' => 'INV-2026-0001',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-31',
        'status' => 'sent',
        'total_amount' => 500_000,
        ...$attributes,
    ]);

    InvoiceItem::query()->create([
        'public_id' => '01m3baris',
        'invoice_public_id' => $invoice->public_id,
        'description' => 'Kopi 5 kg',
        'quantity' => 5,
        'unit_price' => 100_000,
    ]);

    return $invoice;
}

it('drafts an invoice on the phone and queues it for the server', function () {
    Livewire::test('invoice')
        ->set('clientPublicId', '01m3klien')
        ->set('issueDate', '2026-10-01')
        ->set('dueDate', '2026-10-15')
        ->set('items', [['description' => 'Kopi 5 kg', 'quantity' => '5', 'unit_price' => '100000']])
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->sole();

    expect((float) $invoice->total_amount)->toBe(500_000.0)
        ->and($invoice->status)->toBe('draft')
        ->and($invoice->invoice_number)->toBeNull()
        ->and($invoice->is_dirty)->toBeTrue()
        ->and($invoice->items)->toHaveCount(1);
});

it('keeps the invoice screen shut for an account that is not premium', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);

    Livewire::test('invoices')->assertSee('Fitur premium');
    Livewire::test('invoice')->assertRedirect(route('invoices'));
});

it('refuses a line with no description', function () {
    Livewire::test('invoice')
        ->set('clientPublicId', '01m3klien')
        ->set('issueDate', '2026-10-01')
        ->set('dueDate', '2026-10-15')
        ->set('items', [['description' => '', 'quantity' => '1', 'unit_price' => '50000']])
        ->call('save')
        ->assertHasErrors('items.0.description');

    expect(Invoice::query()->count())->toBe(0);
});

it('replaces the lines when one is taken out', function () {
    $invoice = syncedInvoice();

    Livewire::test('invoice', ['publicId' => $invoice->public_id])
        ->call('startEditing')
        ->set('items', [['description' => 'Kopi 2 kg', 'quantity' => '2', 'unit_price' => '100000']])
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh()->load('items');

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->description)->toBe('Kopi 2 kg')
        ->and((float) $invoice->total_amount)->toBe(200_000.0);
});

it('settles an invoice in the field and moves the balance right away', function () {
    $invoice = syncedInvoice();

    Livewire::test('invoice', ['publicId' => $invoice->public_id])
        ->set('paymentAccountPublicId', '01m3kas')
        ->set('paidAt', '2026-10-10')
        ->call('markAsPaid')
        ->assertHasNoErrors();

    $transaction = Transaction::query()->sole();
    $payment = InvoicePayment::query()->sole();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) Account::query()->sole()->current_balance)->toBe(500_000.0)
        ->and($transaction->is_dirty)->toBeFalse()
        ->and($payment->transaction_public_id)->toBe($transaction->public_id);
});

it('holds back the settle form until the invoice has been sent', function () {
    $invoice = syncedInvoice(['status' => 'draft']);

    Livewire::test('invoice', ['publicId' => $invoice->public_id])
        ->assertDontSee('Tandai lunas')
        ->assertSee('bisa ditandai lunas setelah dikirim');
});

it('refuses to share an invoice the server has never seen', function () {
    $invoice = syncedInvoice(['is_dirty' => true, 'invoice_number' => null]);

    Livewire::test('invoice', ['publicId' => $invoice->public_id])
        ->call('share')
        ->assertSee('belum sampai ke server');

    Http::assertNothingSent();
});

it('asks the server for a share link and marks the invoice sent', function () {
    Http::fake([
        '*/invoices/*/share' => Http::response([
            'invoice_number' => 'INV-2026-0001',
            'url' => 'https://rakku.test/invoices/abc/pdf?signature=xyz',
            'message' => 'Halo Toko Sinar,',
            'status' => 'sent',
        ]),
    ]);

    $invoice = syncedInvoice(['status' => 'draft']);

    Livewire::test('invoice', ['publicId' => $invoice->public_id])->call('share')->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe('sent');
});

it('says so when the share link cannot be fetched', function () {
    Http::fake(['*/invoices/*/share' => Http::response('', 500)]);

    $invoice = syncedInvoice();

    Livewire::test('invoice', ['publicId' => $invoice->public_id])
        ->call('share')
        ->assertSet('error', 'Link invoice gagal diambil. Coba lagi saat sinyal membaik.');
});

it('sends invoices and settlements along on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::response(['applied' => 2, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z']),
    ]);

    $invoice = syncedInvoice(['is_dirty' => true]);
    InvoicePayment::query()->create([
        'invoice_public_id' => $invoice->public_id,
        'account_public_id' => '01m3kas',
        'transaction_public_id' => '01m3trx',
        'paid_at' => '2026-10-10',
    ]);

    app(SyncEngine::class)->push();

    Http::assertSent(function ($request): bool {
        return ($request['invoices'][0]['items'][0]['description'] ?? null) === 'Kopi 5 kg'
            && ($request['invoice_payments'][0]['transaction_public_id'] ?? null) === '01m3trx';
    });

    expect($invoice->fresh()->is_dirty)->toBeFalse()
        ->and(InvoicePayment::query()->count())->toBe(0);
});

it('brings invoices and clients from the server into the phone', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'clients' => [['public_id' => '01m3klien', 'name' => 'Toko Sinar', 'email' => null, 'phone' => '0812', 'address' => null]],
            'invoices' => [[
                'public_id' => '01m3invoice',
                'client_public_id' => '01m3klien',
                'invoice_number' => 'INV-2026-0007',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-31',
                'notes' => null,
                'status' => 'sent',
                'total_amount' => 750_000,
                'items' => [['public_id' => '01m3baris', 'description' => 'Gula 10 kg', 'quantity' => 10, 'unit_price' => 75_000]],
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    $invoice = Invoice::query()->sole();

    expect($invoice->invoice_number)->toBe('INV-2026-0007')
        ->and($invoice->items)->toHaveCount(1)
        ->and(Client::query()->sole()->phone)->toBe('0812');
});

it('does not let the server undo a settlement that has not been sent yet', function () {
    $invoice = syncedInvoice();
    InvoicePayment::query()->create([
        'invoice_public_id' => $invoice->public_id,
        'account_public_id' => '01m3kas',
        'paid_at' => '2026-10-10',
    ]);
    $invoice->update(['status' => 'paid']);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'invoices' => [[
                'public_id' => $invoice->public_id,
                'client_public_id' => '01m3klien',
                'invoice_number' => 'INV-2026-0001',
                'issue_date' => '2026-10-01',
                'due_date' => '2026-10-31',
                'notes' => null,
                'status' => 'sent',
                'total_amount' => 500_000,
                'items' => [],
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('survives a server that does not know about invoices yet', function () {
    syncedInvoice();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Invoice::query()->count())->toBe(1);
});

it('takes down a new client in the field and queues it with the invoice', function () {
    Livewire::test('invoice')
        ->call('addClient')
        ->assertHasErrors('newClientName')
        ->set('newClientName', 'Warung Bu Sri')
        ->set('newClientPhone', '081234567890')
        ->call('addClient')
        ->assertHasNoErrors()
        ->assertSet('isAddingClient', false)
        ->set('issueDate', '2026-10-01')
        ->set('dueDate', '2026-10-15')
        ->set('items', [['description' => 'Kopi 1 kg', 'quantity' => '1', 'unit_price' => '100000']])
        ->call('save')
        ->assertHasNoErrors();

    $client = Client::query()->where('name', 'Warung Bu Sri')->sole();

    expect($client->is_dirty)->toBeTrue()
        ->and($client->phone)->toBe('081234567890')
        ->and(Invoice::query()->sole()->client_public_id)->toBe($client->public_id);
});

it('sends a client made on the phone along with the invoice', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::response(['applied' => 2, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z']),
    ]);

    Client::query()->create(['public_id' => '01m3klienbaru', 'name' => 'Warung Bu Sri', 'is_dirty' => true]);

    app(SyncEngine::class)->push();

    Http::assertSent(fn ($request): bool => ($request['clients'][0]['name'] ?? null) === 'Warung Bu Sri');

    expect(Client::query()->where('public_id', '01m3klienbaru')->sole()->is_dirty)->toBeFalse();
});

it('keeps a client the phone has not sent yet, even when the server list lacks it', function () {
    Client::query()->create(['public_id' => '01m3klienbaru', 'name' => 'Warung Bu Sri', 'is_dirty' => true]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'clients' => [['public_id' => '01m3klien', 'name' => 'Toko Sinar', 'email' => null, 'phone' => null, 'address' => null]],
            'invoices' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Client::query()->pluck('public_id')->all())->toContain('01m3klienbaru');
});
