<?php

use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Jawaban server saat pendaftaran berhasil.
 */
function fakeRegisterAccepted(): void
{
    Http::fake([
        '*/api/v1/register' => Http::response([
            'message' => 'Akun dibuat.',
            'email' => 'budi@contoh.test',
        ], 201),
    ]);
}

it('offers the way in from the login screen, not a browser', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Belum punya akun? Daftar')
        ->assertDontSee('rakku.iqbalfhz.my.id');
});

it('registers and then stops at the check-your-email screen', function () {
    fakeRegisterAccepted();

    Livewire::test('register')
        ->set('name', 'Budi')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Cek email')
        ->assertSee('budi@contoh.test');

    // Tidak ada token: verifikasi email dulu, baru boleh masuk.
    expect(app(TokenStore::class)->isSignedIn())->toBeFalse();
});

it('refuses a password too short before bothering the server', function () {
    Http::fake();

    Livewire::test('register')
        ->set('name', 'Budi')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'pendek')
        ->call('submit')
        ->assertHasErrors('password')
        ->assertSee('minimal 8 karakter');

    Http::assertNothingSent();
});

it('insists on all three fields', function () {
    Http::fake();

    Livewire::test('register')->call('submit')->assertHasErrors(['name', 'email', 'password']);

    Http::assertNothingSent();
});

it('passes on the reason when the email is already taken', function () {
    Http::fake([
        '*/api/v1/register' => Http::response([
            'message' => 'Data yang diberikan tidak valid.',
            'errors' => ['email' => ['Email ini sudah terdaftar. Masuk saja dengan email itu.']],
        ], 422),
    ]);

    Livewire::test('register')
        ->set('name', 'Budi')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('submit')
        ->assertSet('error', 'Email ini sudah terdaftar. Masuk saja dengan email itu.');
});

it('says so plainly when too many accounts are opened at once', function () {
    Http::fake(['*/api/v1/register' => Http::response('', 429)]);

    Livewire::test('register')
        ->set('name', 'Budi')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('submit')
        ->assertSet('error', 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.');
});

it('says so plainly when the server cannot be reached', function () {
    Http::fake(['*/api/v1/register' => Http::response('', 500)]);

    Livewire::test('register')
        ->set('name', 'Budi')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia-sekali')
        ->call('submit')
        ->assertSet('error', 'Server sedang tidak bisa dihubungi. Coba lagi sebentar.');
});

it('keeps a signed-in phone away from the registration screen', function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');

    $this->get(route('register'))->assertRedirect(route('home'));
});
