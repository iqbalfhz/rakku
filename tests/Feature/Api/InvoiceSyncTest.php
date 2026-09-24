<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());
    $this->book = $this->user->books()->first();
    $this->account = Account::factory()->for($this->book)->create(['initial_balance' => 0]);
    $this->client = Client::factory()->for($this->book)->create(['name' => 'Toko Sinar']);

    Sanctum::actingAs($this->user);
});

/**
 * Invoice lengkap dengan satu baris isi.
 */
function invoiceWithItem(float $unitPrice = 500_000, array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->for(test()->book)->for(test()->client)->create($attributes);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => $unitPrice]);

    return $invoice;
}

it('hands the phone the clients and the outstanding invoices', function () {
    $invoice = invoiceWithItem();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('clients.0.name'))->toBe('Toko Sinar')
        ->and($response->json('invoices'))->toHaveCount(1)
        ->and($response->json('invoices.0.public_id'))->toBe($invoice->public_id)
        ->and($response->json('invoices.0.invoice_number'))->toBe($invoice->invoice_number)
        ->and((float) $response->json('invoices.0.total_amount'))->toBe(500_000.0)
        ->and($response->json('invoices.0.items'))->toHaveCount(1);
});

it('leaves invoices out for an account that is not premium', function () {
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();
    invoiceWithItem();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('invoices'))->toBeEmpty()
        ->and($response->json('clients'))->toBeEmpty();
});

it('accepts an invoice drafted offline and gives it a number', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'invoices' => [[
            'public_id' => $publicId,
            'client_public_id' => $this->client->public_id,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'notes' => 'Pesanan bulan ini',
            'items' => [
                ['public_id' => Str::lower((string) Str::ulid()), 'description' => 'Kopi 5 kg', 'quantity' => 5, 'unit_price' => 100_000],
            ],
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $invoice = Invoice::query()->where('public_id', $publicId)->sole();

    expect($invoice->invoice_number)->toStartWith('INV-')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->loadMissing('items')->totalAmount())->toBe(500_000.0);
});

it('replaces the items when the phone sends a shorter list', function () {
    $invoice = invoiceWithItem();
    $keptItem = $invoice->items->first();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'invoices' => [[
            'public_id' => $invoice->public_id,
            'client_public_id' => $this->client->public_id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [
                ['public_id' => $keptItem->public_id, 'description' => 'Kopi 2 kg', 'quantity' => 2, 'unit_price' => 100_000],
            ],
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $invoice->refresh()->load('items');

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->items->first()->description)->toBe('Kopi 2 kg')
        ->and($invoice->totalAmount())->toBe(200_000.0);
});

it('settles an invoice paid in the field and keeps the transaction id the phone made', function () {
    $invoice = invoiceWithItem(attributes: ['status' => InvoiceStatus::Sent]);
    $transactionPublicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'invoice_payments' => [[
            'invoice_public_id' => $invoice->public_id,
            'account_public_id' => $this->account->public_id,
            'transaction_public_id' => $transactionPublicId,
            'paid_at' => '2026-10-10',
        ]],
    ])->assertJson(['applied' => 1, 'skipped' => 0]);

    $transaction = Transaction::query()->sole();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($transaction->public_id)->toBe($transactionPublicId)
        ->and((float) $transaction->amount)->toBe(500_000.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(500_000.0);
});

it('ignores a second attempt to settle the same invoice', function () {
    $invoice = invoiceWithItem(attributes: ['status' => InvoiceStatus::Sent]);
    $payload = [
        'invoice_public_id' => $invoice->public_id,
        'account_public_id' => $this->account->public_id,
        'paid_at' => '2026-10-10',
    ];

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [], 'invoice_payments' => [$payload]]);
    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [], 'invoice_payments' => [$payload]])
        ->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Transaction::query()->count())->toBe(1);
});

it('refuses to delete an invoice that is already settled', function () {
    $invoice = invoiceWithItem(attributes: ['status' => InvoiceStatus::Paid]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'invoices' => [[
            'public_id' => $invoice->public_id,
            'client_public_id' => $this->client->public_id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [],
            'is_deleted' => true,
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect($invoice->fresh()->trashed())->toBeFalse();
});

it('passes a deleted invoice on as a tombstone', function () {
    $this->travelTo('2026-10-01 08:00');
    $invoice = invoiceWithItem();
    $since = now()->utc()->toIso8601ZuluString();

    $this->travelTo('2026-10-01 09:00');
    $invoice->delete();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync?since={$since}")->assertSuccessful();

    expect($response->json('invoices.0.public_id'))->toBe($invoice->public_id)
        ->and($response->json('invoices.0.is_deleted'))->toBeTrue();
});

it('hands the phone a share link and marks the draft as sent', function () {
    $invoice = invoiceWithItem();

    $response = $this->postJson("/api/v1/books/{$this->book->public_id}/invoices/{$invoice->public_id}/share")
        ->assertSuccessful();

    expect($response->json('url'))->toContain('signature=')
        ->and($response->json('message'))->toContain($invoice->invoice_number)
        ->and($response->json('status'))->toBe('sent')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('keeps the share link away from an account that is not premium', function () {
    $invoice = invoiceWithItem();
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();

    $this->postJson("/api/v1/books/{$this->book->public_id}/invoices/{$invoice->public_id}/share")
        ->assertForbidden();
});

it('takes a client created in the field along with the invoice that needs it', function () {
    $clientPublicId = Str::lower((string) Str::ulid());
    $invoicePublicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'clients' => [[
            'public_id' => $clientPublicId,
            'name' => 'Warung Bu Sri',
            'phone' => '081234567890',
            'updated_at' => now()->toIso8601String(),
        ]],
        'invoices' => [[
            'public_id' => $invoicePublicId,
            'client_public_id' => $clientPublicId,
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'items' => [
                ['public_id' => Str::lower((string) Str::ulid()), 'description' => 'Kopi 1 kg', 'quantity' => 1, 'unit_price' => 100_000],
            ],
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 2, 'skipped' => 0]);

    $client = Client::query()->where('public_id', $clientPublicId)->sole();

    expect($client->book_id)->toBe($this->book->id)
        ->and($client->phone)->toBe('081234567890')
        ->and(Invoice::query()->where('public_id', $invoicePublicId)->sole()->client_id)->toBe($client->id);
});

it('turns down a client pushed by an account that is not premium', function () {
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'clients' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'name' => 'Warung Bu Sri',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Client::query()->count())->toBe(1);
});
