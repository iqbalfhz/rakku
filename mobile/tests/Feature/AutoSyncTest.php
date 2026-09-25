<?php

use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Services\AutoSync;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
});

/**
 * Jawaban server yang normal: dorongan diterima, lalu tarikan kosong.
 */
function fakeQuietServer(): void
{
    Http::fake([
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);
}

/**
 * Catatan yang menunggu dikirim.
 */
function unsentTransaction(): Transaction
{
    return Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);
}

it('sends what is waiting without anyone pressing a button', function () {
    fakeQuietServer();
    $transaction = unsentTransaction();

    Livewire::test('home')->call('autoSync');

    expect($transaction->fresh()->is_dirty)->toBeFalse()
        ->and($this->tokenStore->lastSyncedAt())->toBe('2026-10-01T03:00:00Z');
});

it('holds back a second look for news that comes too soon after the first', function () {
    fakeQuietServer();

    expect(app(AutoSync::class)->attempt())->toBeTrue()
        ->and(app(AutoSync::class)->attempt())->toBeFalse();

    Http::assertSentCount(1);
});

it('never makes a waiting note wait for the quiet period', function () {
    fakeQuietServer();
    app(AutoSync::class)->attempt();

    unsentTransaction();

    expect(app(AutoSync::class)->attempt())->toBeTrue();
});

it('still backs off after a failure, even with notes waiting', function () {
    Http::fake(['*/api/v1/books/*/sync*' => Http::response('', 500)]);
    unsentTransaction();

    expect(app(AutoSync::class)->attempt())->toBeFalse()
        ->and(app(AutoSync::class)->attempt())->toBeFalse();

    Http::assertSentCount(1);
});

it('tries again once the quiet spell has passed', function () {
    fakeQuietServer();
    app(AutoSync::class)->attempt();

    $this->travel(AutoSync::QUIET_PERIOD_SECONDS + 1)->seconds();

    expect(app(AutoSync::class)->attempt())->toBeTrue();
});

it('goes ahead anyway when the user asks for it outright', function () {
    fakeQuietServer();
    app(AutoSync::class)->attempt();

    expect(app(AutoSync::class)->attempt(force: true))->toBeTrue();

    Http::assertSentCount(2);
});

it('stays quiet on a phone nobody has signed in on', function () {
    fakeQuietServer();
    $this->tokenStore->forget();

    expect(app(AutoSync::class)->attempt())->toBeFalse();

    Http::assertNothingSent();
});

it('swallows a failure instead of nagging the user', function () {
    Http::fake(['*/api/v1/books/*/sync*' => Http::response('', 500)]);
    $transaction = unsentTransaction();

    Livewire::test('home')->call('autoSync')->assertSet('syncError', null);

    expect($transaction->fresh()->is_dirty)->toBeTrue();
});

it('keeps saying it plainly when a manual sync fails', function () {
    Http::fake(['*/api/v1/books/*/sync*' => Http::response('', 500)]);

    Livewire::test('home')
        ->call('sync')
        ->assertSet('syncError', 'Gagal menyambung ke server. Catatan di ponsel tetap aman dan akan dikirim saat sinyal kembali.');
});

/**
 * Dulu ini wire:init. Di ponsel, permintaan itu berangkat sebelum NativePHP
 * memasang pencegat POST-nya, sampai di PHP tanpa body, lalu dijawab 419 —
 * pengguna melihat "Halaman ini sudah kedaluwarsa" setiap kali membuka aplikasi.
 */
it('starts the sync by itself once the page is ready for it', function () {
    Livewire::test('home')
        ->assertSeeHtml('x-on:bridge-ready.window')
        ->assertDontSeeHtml('wire:init');
});

it('counts everything that is waiting, not only transactions', function () {
    unsentTransaction();
    Debt::query()->create([
        'public_id' => '01m3utang',
        'type' => 'receivable',
        'counterparty_name' => 'Bu Rina',
        'amount' => 100_000,
        'remaining_amount' => 100_000,
        'is_dirty' => true,
    ]);

    Livewire::test('home')->assertSee('2 catatan menunggu dikirim');
});

it('reaches for the server the moment the signal comes back', function () {
    Livewire::test('home')->assertSeeHtml('x-on:online.window');
});

it('never claims the last sync happened in the future', function () {
    // Jam server selalu sedikit berbeda dari jam ponsel.
    app(TokenStore::class)->rememberSync(now()->addSeconds(36)->toIso8601String());

    Livewire::test('home')->assertSee('Sinkron terakhir: Baru saja');
});

it('says plainly when the phone has never synced', function () {
    Livewire::test('home')->assertSee('Sinkron terakhir: Belum pernah');
});
