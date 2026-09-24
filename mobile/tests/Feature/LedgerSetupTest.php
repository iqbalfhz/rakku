<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Akun yang sudah tersinkron dari server.
 */
function syncedAccount(array $attributes = []): Account
{
    return Account::query()->create([
        'public_id' => '01m3kas',
        'name' => 'Kas Laci',
        'type' => 'cash',
        'initial_balance' => 100_000,
        'current_balance' => 100_000,
        ...$attributes,
    ]);
}

it('opens an account on the phone and queues it for the server', function () {
    Livewire::test('setup')
        ->call('startAddingAccount')
        ->set('accountName', 'BRI Warung')
        ->set('accountType', 'bank')
        ->set('initialBalance', '500000')
        ->call('saveAccount')
        ->assertHasNoErrors();

    $account = Account::query()->sole();

    expect($account->name)->toBe('BRI Warung')
        ->and((float) $account->current_balance)->toBe(500_000.0)
        ->and($account->is_dirty)->toBeTrue();
});

it('shifts the running balance when the opening balance is corrected', function () {
    $account = syncedAccount(['current_balance' => 70_000]);

    Livewire::test('setup')
        ->call('startEditingAccount', $account->id)
        ->set('initialBalance', '150000')
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect((float) $account->fresh()->current_balance)->toBe(120_000.0);
});

it('refuses to drop an account that has already been used', function () {
    $account = syncedAccount();
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => $account->public_id,
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
    ]);

    Livewire::test('setup')
        ->call('removeAccount', $account->id)
        ->assertSee('sudah dipakai mencatat tidak bisa dihapus');

    expect($account->fresh()->is_deleted)->toBeFalse();
});

it('drops an account that was never used', function () {
    $account = syncedAccount();

    Livewire::test('setup')->call('removeAccount', $account->id);

    expect($account->fresh()->is_deleted)->toBeTrue()
        ->and($account->fresh()->is_dirty)->toBeTrue();
});

it('writes a category down on the phone', function () {
    Livewire::test('setup')
        ->set('tab', 'categories')
        ->call('startAddingCategory')
        ->set('categoryName', 'Bensin')
        ->set('categoryType', 'expense')
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect(Category::query()->sole()->name)->toBe('Bensin');
});

it('keeps a removed account out of the picker before it is even sent', function () {
    $account = syncedAccount();
    Category::query()->create(['public_id' => '01m3bensin', 'name' => 'Bensin', 'type' => 'expense']);

    Livewire::test('setup')->call('removeAccount', $account->id);

    Livewire::test('record')->assertDontSee('Kas Laci');
});

it('sends accounts and categories along on the next sync', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::response(['applied' => 2, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z']),
    ]);

    $account = syncedAccount(['is_dirty' => true]);
    Category::query()->create(['public_id' => '01m3bensin', 'name' => 'Bensin', 'type' => 'expense', 'is_dirty' => true]);

    app(SyncEngine::class)->push();

    Http::assertSent(function ($request): bool {
        return ($request['accounts'][0]['name'] ?? null) === 'Kas Laci'
            && ($request['accounts'][0]['initial_balance'] ?? null) === 100000.0
            && ($request['categories'][0]['name'] ?? null) === 'Bensin';
    });

    expect($account->fresh()->is_dirty)->toBeFalse();
});

it('keeps an account the phone has not sent yet, even when the server list lacks it', function () {
    $account = syncedAccount(['is_dirty' => true]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Account::query()->sole()->is($account))->toBeTrue();
});

it('takes the opening balance and usage flag from the server', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [[
                'public_id' => '01m3kas',
                'name' => 'Kas Laci',
                'type' => 'cash',
                'initial_balance' => 100_000,
                'current_balance' => 75_000,
                'has_activity' => true,
                'updated_at' => '2026-10-01T02:00:00Z',
            ]],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    $account = Account::query()->sole();

    expect((float) $account->initial_balance)->toBe(100_000.0)
        ->and($account->has_activity)->toBeTrue()
        ->and($account->canBeRemoved())->toBeFalse();
});

it('survives a server that does not send the new account columns yet', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 75_000]],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    // Tanpa kabar dari server, akun dianggap sudah terpakai supaya tidak terhapus sembarangan.
    expect(Account::query()->sole()->has_activity)->toBeTrue();
});
