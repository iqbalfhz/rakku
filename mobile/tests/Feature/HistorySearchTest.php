<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TokenStore;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Account::query()->create(['public_id' => '01m3bank', 'name' => 'BRI', 'type' => 'bank', 'current_balance' => 0]);
    Category::query()->create(['public_id' => '01m3bensin', 'name' => 'Bensin', 'type' => 'expense']);

    $this->travelTo('2026-10-15');
});

/**
 * Catatan yang sudah tersinkron.
 */
function note(array $attributes = []): Transaction
{
    return Transaction::query()->create([
        'public_id' => 'trx-'.fake()->unique()->numerify('#####'),
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-05',
        ...$attributes,
    ]);
}

it('finds a note by what was written on it', function () {
    note(['description' => 'Beli kertas A4']);
    note(['description' => 'Bayar listrik']);

    Livewire::test('history')
        ->set('search', 'kertas')
        ->assertSee('Beli kertas A4')
        ->assertDontSee('Bayar listrik');
});

it('narrows down to one account', function () {
    note(['description' => 'Dari laci', 'account_public_id' => '01m3kas']);
    note(['description' => 'Dari bank', 'account_public_id' => '01m3bank']);

    Livewire::test('history')
        ->set('accountPublicId', '01m3bank')
        ->assertSee('Dari bank')
        ->assertDontSee('Dari laci');
});

it('narrows down to one category', function () {
    note(['description' => 'Isi bensin', 'category_public_id' => '01m3bensin']);
    note(['description' => 'Tanpa kategori']);

    Livewire::test('history')
        ->set('categoryPublicId', '01m3bensin')
        ->assertSee('Isi bensin')
        ->assertDontSee('Tanpa kategori');
});

it('narrows down to one month', function () {
    note(['description' => 'Bulan ini', 'transaction_date' => '2026-10-05']);
    note(['description' => 'Bulan lalu', 'transaction_date' => '2026-09-20']);

    Livewire::test('history')
        ->set('month', '2026-09')
        ->assertSee('Bulan lalu')
        ->assertDontSee('Bulan ini');
});

it('stacks the filters instead of replacing them', function () {
    note(['description' => 'Bensin dari bank', 'account_public_id' => '01m3bank', 'category_public_id' => '01m3bensin']);
    note(['description' => 'Bensin dari laci', 'account_public_id' => '01m3kas', 'category_public_id' => '01m3bensin']);

    Livewire::test('history')
        ->set('categoryPublicId', '01m3bensin')
        ->set('accountPublicId', '01m3bank')
        ->assertSee('Bensin dari bank')
        ->assertDontSee('Bensin dari laci');
});

it('says how many matched and what they add up to', function () {
    note(['description' => 'Beli kertas', 'type' => 'expense', 'amount' => 30_000]);
    note(['description' => 'Beli kertas lagi', 'type' => 'expense', 'amount' => 20_000]);
    note(['description' => 'Jual kopi', 'type' => 'income', 'amount' => 100_000]);

    Livewire::test('history')
        ->set('search', 'kertas')
        ->assertSee('2 catatan cocok')
        ->assertSee('−Rp 50.000');
});

it('keeps the running total honest across pages', function () {
    foreach (range(1, 60) as $number) {
        note(['description' => "Catatan {$number}", 'amount' => 1_000]);
    }

    // Hanya 50 yang tergambar, tapi totalnya menghitung enam puluh.
    Livewire::test('history')
        ->set('filter', 'expense')
        ->assertSee('60 catatan cocok')
        ->assertSee('−Rp 60.000');
});

it('draws only a pageful at a time and offers the rest', function () {
    foreach (range(1, 60) as $number) {
        note(['description' => "Catatan {$number}"]);
    }

    $component = Livewire::test('history')->assertSee('Tampilkan lebih banyak');

    expect($component->get('limit'))->toBe(50);

    $component->call('showMore')->assertDontSee('Tampilkan lebih banyak');
});

it('starts the list over when the filter changes', function () {
    foreach (range(1, 60) as $number) {
        note(['description' => "Catatan {$number}"]);
    }

    Livewire::test('history')
        ->call('showMore')
        ->set('search', 'Catatan')
        ->assertSet('limit', 50);
});

it('says plainly when nothing matches', function () {
    note(['description' => 'Beli kertas']);

    Livewire::test('history')
        ->set('search', 'sesuatu yang tidak ada')
        ->assertSee('Tidak ada yang cocok')
        ->assertSee('longgarkan saringannya');
});

it('clears every filter at once', function () {
    note(['description' => 'Beli kertas']);

    Livewire::test('history')
        ->set('search', 'kertas')
        ->set('filter', 'income')
        ->set('month', '2026-09')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('filter', 'all')
        ->assertSet('month', '')
        ->assertSee('Beli kertas');
});
