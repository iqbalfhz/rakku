<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\TokenStore;
use Livewire\Livewire;

beforeEach(function () {
    $tokenStore = app(TokenStore::class);
    $tokenStore->rememberSession('token-rahasia', 'Budi');
    $tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Tujuh pintasan tadinya daftar bertingkat, sekarang petak berikon. Mengubah
 * bentuknya adalah tempat paling gampang untuk kehilangan satu tujuan tanpa ada
 * yang sadar — layarnya masih ada, hanya tidak bisa lagi dicapai dari beranda.
 */
it('still reaches every screen the shortcut list used to reach', function (string $route) {
    Livewire::test('home')->assertSeeHtml('href="'.route($route).'"');
})->with(['transfers', 'setup', 'report', 'budgets', 'invoices', 'recurring', 'account']);

it('gives every shortcut a name, not an icon on its own', function () {
    $home = Livewire::test('home');

    collect(['Pindah uang', 'Akun & kategori', 'Laporan', 'Anggaran', 'Invoice', 'Transaksi berulang', 'Akun saya'])
        ->each(fn (string $title) => $home->assertSee($title));
});

/**
 * Buku kas sering dibuka di depan orang lain.
 */
it('offers to cover the balance', function () {
    Livewire::test('home')
        ->assertSeeHtml('class="peek"')
        ->assertSee('Rp ••••••');
});

/**
 * Sinkron sudah berjalan sendiri dari empat arah, jadi menawarkan tombolnya
 * terus-menerus hanya memenuhi layar dengan pertanyaan yang tidak perlu dijawab.
 */
it('hides the manual sync button while there is nothing to worry about', function () {
    app(TokenStore::class)->rememberSyncAttempt(succeeded: true);

    Livewire::test('home')->assertDontSee('Sinkronkan sekarang');
});

it('keeps syncing by itself even with the button gone', function () {
    app(TokenStore::class)->rememberSyncAttempt(succeeded: true);

    Livewire::test('home')
        ->assertDontSee('Sinkronkan sekarang')
        ->assertSeeHtml('wire:poll.60s="autoSync"')
        ->assertSeeHtml('x-on:bridge-ready.window');
});

it('offers the button back when notes are still waiting to be sent', function () {
    app(TokenStore::class)->rememberSyncAttempt(succeeded: true);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('home')->assertSee('Sinkronkan sekarang');
});

/**
 * Sinkron otomatis sengaja pendiam. Tanpa tombol ini, token yang dicabut atau
 * server yang bermasalah tidak akan pernah terdengar.
 */
it('offers the button back after a sync that failed', function () {
    app(TokenStore::class)->rememberSyncAttempt(succeeded: false);

    Livewire::test('home')->assertSee('Sinkronkan sekarang');
});

/**
 * Masthead keempat layar tab dipadatkan, dan yang dibuang hanyalah hiasan.
 * Yang membawa keterangan tetap tinggal — di beranda, itu penukar buku dan
 * kabar kapan terakhir tersinkron.
 */
it('keeps the book switcher and the sync status in the shortened masthead', function () {
    Livewire::test('home')
        ->assertSeeHtml('href="'.route('books').'"')
        ->assertSee('Warung Kopi')
        ->assertSee('Sinkron terakhir');
});
