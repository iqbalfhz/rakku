<?php

use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Jawaban server yang normal saat login berhasil.
 *
 * @param  list<array{public_id: string, name: string, is_default: bool}>|null  $books
 */
function fakeLoginResponse(?array $books = null): void
{
    Http::fake([
        '*/api/v1/login' => Http::response([
            'token' => 'token-rahasia',
            'user' => ['name' => 'Budi', 'email' => 'budi@rakku.test'],
            'books' => $books ?? [
                ['public_id' => '01m3abcdefghijklmnopqrstuv', 'name' => 'Pribadi', 'is_default' => true],
            ],
        ]),
    ]);
}

it('remembers the session and the default book after signing in', function () {
    fakeLoginResponse([
        ['public_id' => '01m3aaaaaaaaaaaaaaaaaaaaaa', 'name' => 'Warung Kopi', 'is_default' => false],
        ['public_id' => '01m3bbbbbbbbbbbbbbbbbbbbbb', 'name' => 'Pribadi', 'is_default' => true],
    ]);

    Livewire::test('login')
        ->set('email', 'budi@rakku.test')
        ->set('password', 'rahasia-123')
        ->call('submit')
        ->assertRedirect(route('home'));

    $tokenStore = app(TokenStore::class);

    expect($tokenStore->isSignedIn())->toBeTrue()
        ->and($tokenStore->token())->toBe('token-rahasia')
        ->and($tokenStore->userName())->toBe('Budi')
        ->and($tokenStore->bookName())->toBe('Pribadi');
});

it('shows the reason when the server rejects the credentials', function () {
    Http::fake([
        '*/api/v1/login' => Http::response(['message' => 'Email atau kata sandi salah.'], 422),
    ]);

    Livewire::test('login')
        ->set('email', 'budi@rakku.test')
        ->set('password', 'salah-total')
        ->call('submit')
        ->assertSet('error', 'Email atau kata sandi salah.')
        ->assertNoRedirect();

    expect(app(TokenStore::class)->isSignedIn())->toBeFalse();
});

it('says plainly when the server cannot be reached', function () {
    Http::fake(['*/api/v1/login' => Http::response('', 500)]);

    Livewire::test('login')
        ->set('email', 'budi@rakku.test')
        ->set('password', 'rahasia-123')
        ->call('submit')
        ->assertSet('error', 'Server sedang tidak bisa dihubungi. Coba lagi sebentar.');
});

it('asks for an email and a password before calling the server', function () {
    Http::fake();

    Livewire::test('login')
        ->call('submit')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);

    Http::assertNothingSent();
});

it('sends the phone straight past the login screen once signed in', function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3abcdefghijklmnopqrstuv', 'Pribadi');

    $this->get('/beranda')->assertSuccessful()->assertSee('Halo, Budi');
});

it('keeps the home screen shut while nobody is signed in', function () {
    $this->get('/beranda')->assertRedirect(route('login'));
});

it('forgets everything when the user signs out', function () {
    Http::fake(['*/api/v1/logout' => Http::response([])]);
    $tokenStore = app(TokenStore::class);
    $tokenStore->rememberSession('token-rahasia', 'Budi');
    $tokenStore->rememberBook('01m3abcdefghijklmnopqrstuv', 'Pribadi');

    Livewire::test('home')
        ->call('signOut')
        ->assertRedirect(route('login'));

    expect($tokenStore->isSignedIn())->toBeFalse()
        ->and($tokenStore->bookPublicId())->toBeNull();
});
