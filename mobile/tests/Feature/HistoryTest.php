<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TokenStore;
use Livewire\Livewire;

beforeEach(function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
});

function recordTransaction(string $publicId, string $type, float $amount, string $date, string $description): Transaction
{
    return Transaction::query()->create([
        'public_id' => $publicId,
        'account_public_id' => '01m3kas',
        'type' => $type,
        'amount' => $amount,
        'description' => $description,
        'transaction_date' => $date,
    ]);
}

it('groups the entries by day with a running total', function () {
    recordTransaction('01m3a', 'income', 100_000, '2026-10-01', 'Jualan pagi');
    recordTransaction('01m3b', 'expense', 40_000, '2026-10-01', 'Beli gula');
    recordTransaction('01m3c', 'expense', 15_000, '2026-09-30', 'Parkir');

    Livewire::test('history')
        ->assertSee('Jualan pagi')
        ->assertSee('Beli gula')
        ->assertSee('Parkir')
        ->assertSee('Rp 60.000');
});

it('filters to income or expense only', function () {
    recordTransaction('01m3a', 'income', 100_000, '2026-10-01', 'Jualan pagi');
    recordTransaction('01m3b', 'expense', 40_000, '2026-10-01', 'Beli gula');

    Livewire::test('history')
        ->set('filter', 'income')
        ->assertSee('Jualan pagi')
        ->assertDontSee('Beli gula')
        ->set('filter', 'expense')
        ->assertSee('Beli gula')
        ->assertDontSee('Jualan pagi');
});

it('hides an entry the moment it is deleted', function () {
    $transaction = recordTransaction('01m3a', 'expense', 40_000, '2026-10-01', 'Beli gula');

    Livewire::test('history')
        ->call('remove', $transaction->id)
        ->assertDontSee('Beli gula');

    expect($transaction->fresh()->is_deleted)->toBeTrue();
});

it('shows the tab bar only after signing in', function () {
    $this->get(route('home'))->assertSee('Riwayat');

    app(TokenStore::class)->forget();

    $this->get('/')->assertDontSee('tabs__tab', escape: false);
});
