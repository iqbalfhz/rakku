<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\PlanGate;
use App\Services\TokenStore;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => null]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Category::query()->create(['public_id' => '01m3bahan', 'name' => 'Bahan Baku', 'type' => 'expense']);

    $this->travelTo('2026-10-15');
});

/**
 * Catatan yang sudah tersinkron, siap dijumlah laporan.
 */
function recordedTransaction(array $attributes = []): Transaction
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

it('adds the month up into money in, money out, and what is left', function () {
    recordedTransaction(['type' => 'income', 'amount' => 500_000]);
    recordedTransaction(['type' => 'expense', 'amount' => 200_000]);

    Livewire::test('report')
        ->assertSee('Rp 500.000')
        ->assertSee('Rp 200.000')
        ->assertSee('Rp 300.000')
        ->assertSee('Surplus');
});

it('calls the month a deficit when more went out than came in', function () {
    recordedTransaction(['type' => 'income', 'amount' => 100_000]);
    recordedTransaction(['type' => 'expense', 'amount' => 250_000]);

    Livewire::test('report')->assertSee('Defisit')->assertSee('Rp 150.000');
});

/**
 * Dulu tes ini memastikan nominal bulan lain tidak muncul di mana pun di halaman.
 * Sejak ada grafik enam bulan, halaman ini memang menampilkannya dengan sengaja —
 * jadi yang diperiksa sekarang angka yang dijumlah untuk bulan yang diminta.
 */
it('adds up only the month the reader asked for', function () {
    recordedTransaction(['type' => 'income', 'amount' => 500_000, 'transaction_date' => '2026-09-20']);
    recordedTransaction(['type' => 'income', 'amount' => 70_000, 'transaction_date' => '2026-10-05']);

    $report = Livewire::test('report')->assertSee('Rp 70.000');

    expect($report->instance()->totals())->toBe(['income' => 70_000.0, 'expense' => 0.0, 'net' => 70_000.0]);

    $report->set('month', '2026-09');

    expect($report->instance()->totals())->toBe(['income' => 500_000.0, 'expense' => 0.0, 'net' => 500_000.0]);
});

it('shows six months of trend, counting the empty ones as zero', function () {
    recordedTransaction(['type' => 'income', 'amount' => 500_000, 'transaction_date' => '2026-10-05']);

    $trend = Livewire::test('report')->instance()->trend();

    expect($trend)->toHaveCount(6)
        ->and(array_column($trend, 'short'))->toBe(['Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt'])
        ->and($trend[0])->toMatchArray(['income' => 'Rp 0', 'incomeHeight' => 0.0])
        ->and($trend[5])->toMatchArray(['income' => 'Rp 500.000', 'incomeHeight' => 100.0]);
});

/**
 * Kalau masuk dan keluar diskalakan sendiri-sendiri, dua batang setinggi sama
 * bisa berarti nominal yang jauh berbeda — grafiknya berbohong sambil terlihat rapi.
 */
it('scales money in and money out against one shared peak', function () {
    recordedTransaction(['type' => 'income', 'amount' => 400_000, 'transaction_date' => '2026-10-05']);
    recordedTransaction(['type' => 'expense', 'amount' => 100_000, 'transaction_date' => '2026-10-06']);

    $trend = Livewire::test('report')->instance()->trend();

    expect($trend[5])->toMatchArray(['incomeHeight' => 100.0, 'expenseHeight' => 25.0]);
});

it('leaves a note that was deleted but not sent yet out of the trend', function () {
    recordedTransaction(['type' => 'income', 'amount' => 500_000, 'transaction_date' => '2026-10-05']);
    recordedTransaction(['type' => 'income', 'amount' => 80_000, 'transaction_date' => '2026-10-06', 'is_deleted' => true, 'is_dirty' => true]);

    $trend = Livewire::test('report')->instance()->trend();

    expect($trend[5]['income'])->toBe('Rp 500.000');
});

it('says so plainly instead of drawing an empty chart', function () {
    Livewire::test('report')
        ->assertSee('Belum ada catatan di rentang ini.')
        ->assertDontSeeHtml('chart__plot');
});

it('keeps the trend behind the premium gate', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);
    recordedTransaction(['type' => 'income', 'amount' => 500_000, 'transaction_date' => '2026-10-05']);

    Livewire::test('report')->assertDontSeeHtml('chart__plot');
});

it('leaves out a note that was deleted but not sent yet', function () {
    recordedTransaction(['type' => 'income', 'amount' => 500_000]);
    recordedTransaction(['type' => 'income', 'amount' => 80_000, 'is_deleted' => true, 'is_dirty' => true]);

    Livewire::test('report')->assertSee('Rp 500.000')->assertDontSee('Rp 580.000');
});

it('breaks the spending down by category with its share', function () {
    recordedTransaction(['amount' => 300_000, 'category_public_id' => '01m3bahan']);
    recordedTransaction(['amount' => 100_000]);

    Livewire::test('report')
        ->assertSee('Bahan Baku')
        ->assertSee('75% dari total')
        ->assertSee('Tanpa kategori')
        ->assertSee('25% dari total');
});

it('shows the margin once there is income to measure against', function () {
    recordedTransaction(['type' => 'income', 'amount' => 400_000]);
    recordedTransaction(['type' => 'expense', 'amount' => 100_000]);

    Livewire::test('report')->assertSee('Margin 75%');
});

it('says plainly when there is no income to measure against', function () {
    recordedTransaction(['type' => 'expense', 'amount' => 100_000]);

    Livewire::test('report')->assertSee('Belum ada pendapatan bulan ini');
});

it('keeps the profit and loss breakdown shut for an account that is not premium', function () {
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);
    recordedTransaction(['type' => 'income', 'amount' => 500_000]);

    Livewire::test('report')
        ->assertSee('Rp 500.000')
        ->assertSee('Fitur premium')
        ->assertDontSee('Pengeluaran per kategori');
});

it('admits when the month asked for is older than what the phone holds', function () {
    recordedTransaction(['transaction_date' => '2026-10-05']);

    Livewire::test('report')
        ->assertDontSee('lebih tua daripada catatan')
        ->set('month', '2026-06')
        ->assertSee('lebih tua daripada catatan');
});

/**
 * Batang setipis apa pun tetap terbaca sebagai "ada uang masuk", jadi nol harus
 * benar-benar tidak menggambar apa pun — sementara nominal kecil yang bukan nol
 * justru tidak boleh hilang.
 */
it('draws nothing for an empty month but keeps a sliver for a small one', function () {
    recordedTransaction(['type' => 'income', 'amount' => 1_000_000, 'transaction_date' => '2026-10-05']);
    recordedTransaction(['type' => 'income', 'amount' => 1_000, 'transaction_date' => '2026-09-05']);

    $trend = Livewire::test('report')->instance()->trend();

    expect($trend[0]['incomeHeight'])->toBe(0.0)
        ->and($trend[4]['incomeHeight'])->toBe(1.5)
        ->and($trend[5]['incomeHeight'])->toBe(100.0);
});
