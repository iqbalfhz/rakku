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
 * Jawaban tarikan dari server, dengan bagian yang tidak diisi dibiarkan kosong.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakePullResponse(array $overrides = []): void
{
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response(array_merge([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ], $overrides)),
    ]);
}

it('copies the whole book into the phone on the first pull', function () {
    fakePullResponse([
        'accounts' => [
            ['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 250000],
        ],
        'categories' => [
            ['public_id' => '01m3jual', 'name' => 'Penjualan', 'type' => 'income'],
        ],
        'transactions' => [[
            'public_id' => '01m3trx',
            'account_public_id' => '01m3kas',
            'category_public_id' => '01m3jual',
            'type' => 'income',
            'amount' => 250000,
            'description' => 'Kopi 10 gelas',
            'transaction_date' => '2026-10-01',
            'has_receipt' => false,
            'is_deleted' => false,
            'updated_at' => '2026-10-01T02:00:00Z',
        ]],
    ]);

    app(SyncEngine::class)->pull();

    expect(Account::query()->sole())
        ->name->toBe('Kas Laci')
        ->and((float) Account::query()->sole()->current_balance)->toBe(250000.0)
        ->and(Category::query()->sole()->name)->toBe('Penjualan')
        ->and(Transaction::query()->sole())
        ->description->toBe('Kopi 10 gelas')
        ->and($this->tokenStore->lastSyncedAt())->toBe('2026-10-01T03:00:00Z');
});

it('asks only for what changed once it has synced before', function () {
    $this->tokenStore->rememberSync('2026-10-01T03:00:00Z');
    fakePullResponse();

    app(SyncEngine::class)->pull();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'since=2026-10-01T03%3A00%3A00Z'));
});

it('drops transactions the server reports as deleted', function () {
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 10000,
        'transaction_date' => '2026-10-01',
    ]);

    fakePullResponse([
        'transactions' => [[
            'public_id' => '01m3trx',
            'account_public_id' => '01m3kas',
            'category_public_id' => null,
            'type' => 'expense',
            'amount' => 10000,
            'description' => null,
            'transaction_date' => '2026-10-01',
            'has_receipt' => false,
            'is_deleted' => true,
            'updated_at' => '2026-10-01T02:30:00Z',
        ]],
    ]);

    app(SyncEngine::class)->pull();

    expect(Transaction::query()->count())->toBe(0);
});

it('removes accounts that no longer exist on the server', function () {
    Account::query()->create(['public_id' => '01m3lama', 'name' => 'Dompet Lama', 'type' => 'cash', 'current_balance' => 0]);

    fakePullResponse([
        'accounts' => [
            ['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 5000],
        ],
    ]);

    app(SyncEngine::class)->pull();

    expect(Account::query()->pluck('public_id')->all())->toBe(['01m3kas']);
});

it('keeps what is already on the phone when the server cannot be reached', function () {
    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 75000]);
    Http::fake(['*/api/v1/books/*/sync*' => Http::response('', 500)]);

    Livewire::test('home')
        ->call('sync')
        ->assertSet('syncError', 'Gagal menyambung ke server. Catatan di ponsel tetap aman.');

    expect(Account::query()->count())->toBe(1)
        ->and($this->tokenStore->lastSyncedAt())->toBeNull();
});

it('shows the balance and the latest entries on the home screen', function () {
    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 120000]);
    Account::query()->create(['public_id' => '01m3bca', 'name' => 'BCA', 'type' => 'bank', 'current_balance' => 380000]);
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'income',
        'amount' => 250000,
        'description' => 'Kopi 10 gelas',
        'transaction_date' => '2026-10-01',
    ]);

    Livewire::test('home')
        ->assertSee('500.000')
        ->assertSee('Kopi 10 gelas')
        ->assertSee('Kas Laci');
});
