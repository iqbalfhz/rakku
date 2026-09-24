<?php

use App\Models\Account;
use App\Models\Transfer;
use App\Services\LedgerReport;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 500_000]);
    Account::query()->create(['public_id' => '01m3bank', 'name' => 'BRI', 'type' => 'bank', 'current_balance' => 0]);

    $this->travelTo('2026-10-15');
});

/**
 * Pemindahan yang sudah tersinkron dari server.
 */
function syncedTransfer(array $attributes = []): Transfer
{
    return Transfer::query()->create([
        'public_id' => '01m3pindah',
        'from_account_public_id' => '01m3kas',
        'to_account_public_id' => '01m3bank',
        'amount' => 200_000,
        'transfer_date' => '2026-10-01',
        ...$attributes,
    ]);
}

it('moves the money between two accounts at once', function () {
    Livewire::test('transfers')
        ->call('startAdding')
        ->set('fromAccountPublicId', '01m3kas')
        ->set('toAccountPublicId', '01m3bank')
        ->set('amount', '200000')
        ->set('transferDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) Account::query()->where('public_id', '01m3kas')->value('current_balance'))->toBe(300_000.0)
        ->and((float) Account::query()->where('public_id', '01m3bank')->value('current_balance'))->toBe(200_000.0)
        ->and(Transfer::query()->sole()->is_dirty)->toBeTrue();
});

it('stays out of the profit and loss report', function () {
    Livewire::test('transfers')
        ->call('startAdding')
        ->set('fromAccountPublicId', '01m3kas')
        ->set('toAccountPublicId', '01m3bank')
        ->set('amount', '200000')
        ->set('transferDate', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    $totals = app(LedgerReport::class)->totals(
        CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-31'),
    );

    expect($totals['income'])->toBe(0.0)
        ->and($totals['expense'])->toBe(0.0);
});

it('refuses to move money into the very same account', function () {
    Livewire::test('transfers')
        ->call('startAdding')
        ->set('fromAccountPublicId', '01m3kas')
        ->set('toAccountPublicId', '01m3kas')
        ->set('amount', '200000')
        ->set('transferDate', '2026-10-01')
        ->call('save')
        ->assertHasErrors('toAccountPublicId')
        ->assertSee('harus berbeda dari akun asal');

    expect(Transfer::query()->count())->toBe(0);
});

it('leaves no gap when the amount is corrected', function () {
    $transfer = syncedTransfer();
    Account::query()->where('public_id', '01m3kas')->update(['current_balance' => 300_000]);
    Account::query()->where('public_id', '01m3bank')->update(['current_balance' => 200_000]);

    Livewire::test('transfers')
        ->call('startEditing', $transfer->id)
        ->set('amount', '50000')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) Account::query()->where('public_id', '01m3kas')->value('current_balance'))->toBe(450_000.0)
        ->and((float) Account::query()->where('public_id', '01m3bank')->value('current_balance'))->toBe(50_000.0);
});

it('puts the money back when a transfer is cancelled', function () {
    $transfer = syncedTransfer();
    Account::query()->where('public_id', '01m3kas')->update(['current_balance' => 300_000]);
    Account::query()->where('public_id', '01m3bank')->update(['current_balance' => 200_000]);

    Livewire::test('transfers')->call('remove', $transfer->id);

    expect((float) Account::query()->where('public_id', '01m3kas')->value('current_balance'))->toBe(500_000.0)
        ->and((float) Account::query()->where('public_id', '01m3bank')->value('current_balance'))->toBe(0.0)
        ->and($transfer->fresh()->is_deleted)->toBeTrue();
});

it('asks for a second account before offering to move anything', function () {
    Account::query()->where('public_id', '01m3bank')->delete();

    Livewire::test('transfers')
        ->assertSee('Perlu sedikitnya dua akun')
        ->assertDontSee('Pindahkan uang</button>', escape: false);
});

it('sends transfers along on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::response(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z']),
    ]);

    $transfer = syncedTransfer(['is_dirty' => true]);

    app(SyncEngine::class)->push();

    Http::assertSent(fn ($request): bool => ($request['transfers'][0]['from_account_public_id'] ?? null) === '01m3kas');

    expect($transfer->fresh()->is_dirty)->toBeFalse();
});

it('brings transfers from the server into the phone', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'transfers' => [[
                'public_id' => '01m3pindah',
                'from_account_public_id' => '01m3kas',
                'to_account_public_id' => '01m3bank',
                'amount' => 150_000,
                'description' => 'Setor hasil jualan',
                'transfer_date' => '2026-10-01',
                'is_deleted' => false,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Transfer::query()->sole()->description)->toBe('Setor hasil jualan');
});

it('drops a transfer the server says was cancelled', function () {
    syncedTransfer();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
            'transfers' => [['public_id' => '01m3pindah', 'is_deleted' => true, 'updated_at' => '2026-10-01T02:00:00Z']],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Transfer::query()->count())->toBe(0);
});

it('survives a server that does not know about transfers yet', function () {
    syncedTransfer();

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Transfer::query()->count())->toBe(1);
});
