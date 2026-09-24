<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\PlanGate;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => null]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
});

it('offers the way out from inside the app, as the store requires', function () {
    Livewire::test('account')
        ->assertSee('Hapus akun')
        ->assertSee('hilang permanen');
});

it('warns that notes still waiting will go too', function () {
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('account')->assertSee('1 catatan di ponsel ini juga belum terkirim');
});

it('empties the phone and signs out once the account is gone', function () {
    Http::fake(['*/api/v1/account' => Http::response(['message' => 'Akun Anda sudah dihapus.'])]);

    Livewire::test('account')
        ->call('startDeleting')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('deleteAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    expect($this->tokenStore->isSignedIn())->toBeFalse()
        ->and(Account::query()->count())->toBe(0)
        ->and(app(PlanGate::class)->isPremium())->toBeFalse();
});

it('passes on the reason when the confirmation does not match', function () {
    Http::fake([
        '*/api/v1/account' => Http::response([
            'message' => 'Data yang diberikan tidak valid.',
            'errors' => ['password' => ['Kata sandi salah.']],
        ], 422),
    ]);

    Livewire::test('account')
        ->call('startDeleting')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'tebakan-ngawur')
        ->call('deleteAccount')
        ->assertSet('error', 'Kata sandi salah.');

    expect($this->tokenStore->isSignedIn())->toBeTrue()
        ->and(Account::query()->count())->toBe(1);
});

it('keeps everything when the server cannot be reached', function () {
    Http::fake(['*/api/v1/account' => Http::response('', 500)]);

    Livewire::test('account')
        ->call('startDeleting')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('deleteAccount')
        ->assertSet('error', 'Penghapusan gagal. Coba lagi saat sinyal membaik.');

    expect($this->tokenStore->isSignedIn())->toBeTrue()
        ->and(Account::query()->count())->toBe(1);
});

it('will not proceed without both confirmations', function () {
    Http::fake();

    Livewire::test('account')
        ->call('startDeleting')
        ->call('deleteAccount')
        ->assertHasErrors(['email', 'password']);

    Http::assertNothingSent();
});
