<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    $this->account = Account::query()->create([
        'public_id' => '01m3kas',
        'name' => 'Kas Laci',
        'type' => 'cash',
        'current_balance' => 100_000,
    ]);

    Category::query()->create(['public_id' => '01m3belanja', 'name' => 'Belanja', 'type' => 'expense']);
});

it('records a transaction without touching the network', function () {
    Http::fake();

    Livewire::test('record')
        ->set('type', 'expense')
        ->set('amount', '25000')
        ->set('accountPublicId', '01m3kas')
        ->set('categoryPublicId', '01m3belanja')
        ->set('description', 'Beli kertas')
        ->set('transactionDate', '2026-10-01')
        ->call('save')
        ->assertRedirect(route('home'));

    $transaction = Transaction::query()->sole();

    expect($transaction->description)->toBe('Beli kertas')
        ->and($transaction->is_dirty)->toBeTrue()
        ->and(Str::isUlid($transaction->public_id))->toBeTrue()
        ->and((float) $this->account->fresh()->current_balance)->toBe(75_000.0);

    Http::assertNothingSent();
});

it('refuses an empty or zero amount', function () {
    Livewire::test('record')
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['amount']);

    expect(Transaction::query()->count())->toBe(0);
});

it('sends what was recorded offline as soon as it syncs', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push([
                'server_time' => '2026-10-01T03:00:01Z',
                'accounts' => [['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 75000]],
                'categories' => [],
                'transactions' => [],
            ]),
    ]);

    Transaction::query()->create([
        'public_id' => '01m3baru',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'description' => 'Beli kertas',
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    app(SyncEngine::class)->sync();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request['transactions'][0]['public_id'] === '01m3baru'
        && $request['transactions'][0]['description'] === 'Beli kertas');

    expect(Transaction::query()->sole()->is_dirty)->toBeFalse();
});

it('keeps the record marked as pending when the push fails', function () {
    Http::fake(['*/api/v1/books/*/sync' => Http::response('', 500)]);

    Transaction::query()->create([
        'public_id' => '01m3baru',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('home')->call('sync')->assertSet('syncError', fn (?string $error): bool => $error !== null);

    expect(Transaction::query()->sole()->is_dirty)->toBeTrue()
        ->and($this->tokenStore->lastSyncedAt())->toBeNull();
});

it('holds a deletion until the server has been told', function () {
    Http::fake();

    Transaction::query()->create([
        'public_id' => '01m3lama',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 40_000,
        'transaction_date' => '2026-10-01',
    ]);

    Livewire::test('home')
        ->call('remove', Transaction::query()->sole()->id)
        ->assertDontSee('01m3lama');

    $transaction = Transaction::query()->sole();

    expect($transaction->is_deleted)->toBeTrue()
        ->and($transaction->is_dirty)->toBeTrue()
        ->and((float) $this->account->fresh()->current_balance)->toBe(140_000.0);
});

it('drops the deleted row once the server has accepted it', function () {
    Http::fake([
        '*/api/v1/books/*/sync' => Http::sequence()
            ->push(['applied' => 1, 'skipped' => 0, 'server_time' => '2026-10-01T03:00:00Z'])
            ->push(['server_time' => '2026-10-01T03:00:01Z', 'accounts' => [], 'categories' => [], 'transactions' => []]),
    ]);

    Transaction::query()->create([
        'public_id' => '01m3lama',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 40_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
        'is_deleted' => true,
    ]);

    app(SyncEngine::class)->sync();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request['transactions'][0]['is_deleted'] === true);

    expect(Transaction::query()->count())->toBe(0);
});

it('never lets the server overwrite a record that has not been sent yet', function () {
    Transaction::query()->create([
        'public_id' => '01m3baru',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'description' => 'Versi ponsel',
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [[
                'public_id' => '01m3baru',
                'account_public_id' => '01m3kas',
                'category_public_id' => null,
                'type' => 'expense',
                'amount' => 10_000,
                'description' => 'Versi server yang lebih tua',
                'transaction_date' => '2026-10-01',
                'has_receipt' => false,
                'is_deleted' => false,
                'updated_at' => '2026-10-01T01:00:00Z',
            ]],
        ]),
    ]);

    app(SyncEngine::class)->pull();

    expect(Transaction::query()->sole()->description)->toBe('Versi ponsel');
});

it('shows a pending record with its own stamp on the home screen', function () {
    Transaction::query()->create([
        'public_id' => '01m3baru',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'description' => 'Beli kertas',
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('home')
        ->assertSee('Belum terkirim')
        ->assertSee('1 catatan menunggu dikirim');
});
