<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3pribadi', 'Pribadi');
    $this->tokenStore->rememberBooks([
        ['public_id' => '01m3pribadi', 'name' => 'Pribadi', 'is_default' => true],
        ['public_id' => '01m3warung', 'name' => 'Warung Kopi', 'is_default' => false],
    ]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Pribadi', 'type' => 'cash', 'current_balance' => 50_000]);
});

it('lists every book and marks the one being used', function () {
    Livewire::test('books')
        ->assertSee('Pribadi')
        ->assertSee('Warung Kopi')
        ->assertSee('Sedang dibuka');
});

it('swaps the phone over to the chosen book', function () {
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [['public_id' => '01m3laci', 'name' => 'Laci Warung', 'type' => 'cash', 'current_balance' => 900_000]],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    Livewire::test('books')
        ->call('choose', '01m3warung')
        ->assertRedirect(route('home'));

    expect($this->tokenStore->bookName())->toBe('Warung Kopi')
        ->and(Account::query()->pluck('name')->all())->toBe(['Laci Warung']);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '01m3warung/sync'));
});

it('refuses to swap while something is still waiting to be sent', function () {
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('books')
        ->call('choose', '01m3warung')
        ->assertSet('error', 'Masih ada 1 catatan yang belum terkirim. Sinkronkan dulu sebelum pindah buku.');

    expect($this->tokenStore->bookName())->toBe('Pribadi')
        ->and(Transaction::query()->count())->toBe(1);
});

it('still swaps when the first pull fails, and says so', function () {
    Http::fake(['*/api/v1/books/*/sync*' => Http::response('', 500)]);

    Livewire::test('books')
        ->call('choose', '01m3warung')
        ->assertSet('error', 'Buku sudah diganti, tapi isinya belum bisa diambil. Coba sinkronkan lagi nanti.');

    expect($this->tokenStore->bookName())->toBe('Warung Kopi')
        ->and($this->tokenStore->lastSyncedAt())->toBeNull()
        ->and(Account::query()->count())->toBe(0);
});

it('does nothing when the book chosen is the one already open', function () {
    Http::fake();

    Livewire::test('books')->call('choose', '01m3pribadi');

    expect(Account::query()->count())->toBe(1);
    Http::assertNothingSent();
});
