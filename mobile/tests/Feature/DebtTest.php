<?php

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
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

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 100_000]);
});

/**
 * Utang yang sudah ada di ponsel, seolah datang dari server.
 */
function syncedDebt(array $attributes = []): Debt
{
    return Debt::query()->create([
        'public_id' => '01m3utang',
        'type' => 'receivable',
        'counterparty_name' => 'Bu Rina',
        'amount' => 100_000,
        'remaining_amount' => 100_000,
        'status' => 'unpaid',
        ...$attributes,
    ]);
}

it('writes a debt down on the phone and queues it for the server', function () {
    Livewire::test('debt')
        ->set('type', 'receivable')
        ->set('counterpartyName', 'Bu Rina')
        ->set('amount', '150000')
        ->set('dueDate', '2026-11-01')
        ->call('save')
        ->assertHasNoErrors();

    $debt = Debt::query()->sole();

    expect($debt->counterparty_name)->toBe('Bu Rina')
        ->and((float) $debt->remaining_amount)->toBe(150_000.0)
        ->and($debt->status)->toBe('unpaid')
        ->and($debt->is_dirty)->toBeTrue();
});

it('keeps the debt screen shut for an account that is not premium', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);

    Livewire::test('debts')->assertSee('Fitur premium');
    Livewire::test('debt')->assertRedirect(route('debts'));
});

it('records an installment, moves the balance, and keeps the derived transaction out of the queue', function () {
    $debt = syncedDebt();

    Livewire::test('debt', ['publicId' => $debt->public_id])
        ->set('paymentAmount', '40000')
        ->set('paymentAccountPublicId', '01m3kas')
        ->set('paymentDate', '2026-10-01')
        ->call('pay')
        ->assertHasNoErrors();

    $payment = DebtPayment::query()->sole();
    $transaction = Transaction::query()->sole();

    expect((float) $debt->fresh()->remaining_amount)->toBe(60_000.0)
        ->and($debt->fresh()->status)->toBe('unpaid')
        ->and((float) Account::query()->sole()->current_balance)->toBe(140_000.0)
        ->and($payment->transaction_public_id)->toBe($transaction->public_id)
        ->and($payment->is_dirty)->toBeTrue()
        ->and($transaction->is_dirty)->toBeFalse();
});

it('marks a debt paid once the last installment lands', function () {
    $debt = syncedDebt();

    Livewire::test('debt', ['publicId' => $debt->public_id])
        ->set('paymentAmount', '100000')
        ->set('paymentAccountPublicId', '01m3kas')
        ->set('paymentDate', '2026-10-01')
        ->call('pay')
        ->assertHasNoErrors();

    expect($debt->fresh()->status)->toBe('paid');
});

it('refuses an installment larger than what is left', function () {
    $debt = syncedDebt(['remaining_amount' => 60_000]);

    Livewire::test('debt', ['publicId' => $debt->public_id])
        ->set('paymentAmount', '80000')
        ->set('paymentAccountPublicId', '01m3kas')
        ->set('paymentDate', '2026-10-01')
        ->call('pay')
        ->assertHasErrors('paymentAmount');

    expect(DebtPayment::query()->count())->toBe(0);
});

it('refuses to delete a debt that already has installments', function () {
    $debt = syncedDebt();
    DebtPayment::query()->create([
        'public_id' => '01m3cicilan',
        'debt_public_id' => $debt->public_id,
        'account_public_id' => '01m3kas',
        'amount' => 10_000,
        'payment_date' => '2026-10-01',
    ]);

    Livewire::test('debt', ['publicId' => $debt->public_id])->call('remove');

    expect($debt->fresh()->is_deleted)->toBeFalse();
});

it('sends debts and installments along on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 2, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => [], 'debts' => [], 'debt_payments' => []]),
    ]);

    $debt = syncedDebt(['is_dirty' => true]);
    DebtPayment::query()->create([
        'public_id' => '01m3cicilan',
        'debt_public_id' => $debt->public_id,
        'account_public_id' => '01m3kas',
        'transaction_public_id' => '01m3trx',
        'amount' => 40_000,
        'payment_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    app(SyncEngine::class)->push();

    Http::assertSent(function ($request): bool {
        return ($request['debts'][0]['counterparty_name'] ?? null) === 'Bu Rina'
            && ($request['debt_payments'][0]['transaction_public_id'] ?? null) === '01m3trx';
    });

    expect($debt->fresh()->is_dirty)->toBeFalse()
        ->and(DebtPayment::query()->sole()->is_dirty)->toBeFalse();
});

it('brings debts from the server into the phone', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'debts' => [[
                'public_id' => '01m3utang',
                'type' => 'payable',
                'counterparty_name' => 'Pak Andi',
                'amount' => 200_000,
                'remaining_amount' => 150_000,
                'due_date' => '2026-11-01',
                'description' => 'Kulakan gula',
                'status' => 'unpaid',
                'reminder_enabled' => true,
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
            'debt_payments' => [[
                'public_id' => '01m3cicilan',
                'debt_public_id' => '01m3utang',
                'account_public_id' => '01m3kas',
                'transaction_public_id' => '01m3trx',
                'amount' => 50_000,
                'payment_date' => '2026-10-01',
                'notes' => null,
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    $debt = Debt::query()->sole();

    expect($debt->counterparty_name)->toBe('Pak Andi')
        ->and((float) $debt->remaining_amount)->toBe(150_000.0)
        ->and($debt->is_dirty)->toBeFalse()
        ->and($debt->payments()->count())->toBe(1);
});

it('drops the installments too when the server reports the debt gone', function () {
    $debt = syncedDebt();
    DebtPayment::query()->create([
        'public_id' => '01m3cicilan',
        'debt_public_id' => $debt->public_id,
        'account_public_id' => '01m3kas',
        'amount' => 10_000,
        'payment_date' => '2026-10-01',
    ]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'debts' => [['public_id' => $debt->public_id, 'is_deleted' => true, 'updated_at' => '2026-10-01T02:00:00Z']],
            'debt_payments' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Debt::query()->count())->toBe(0)
        ->and(DebtPayment::query()->count())->toBe(0);
});

it('never lets the server overwrite a debt that has not been sent yet', function () {
    $debt = syncedDebt(['counterparty_name' => 'Nama dari ponsel', 'is_dirty' => true]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'debts' => [[
                'public_id' => $debt->public_id,
                'type' => 'receivable',
                'counterparty_name' => 'Nama lama di server',
                'amount' => 100_000,
                'remaining_amount' => 100_000,
                'due_date' => null,
                'description' => null,
                'status' => 'unpaid',
                'reminder_enabled' => true,
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
            'debt_payments' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect($debt->fresh()->counterparty_name)->toBe('Nama dari ponsel');
});

it('survives a server that does not know about debts yet', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect($this->tokenStore->lastSyncedAt())->toBe('2026-10-01T03:00:00Z');
});
