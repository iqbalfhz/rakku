<?php

use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
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

it('pulls the book right after signing in so the home screen is not empty', function () {
    Http::fake([
        '*/api/v1/login' => Http::response([
            'token' => 'token-rahasia',
            'user' => ['name' => 'Budi', 'email' => 'budi@rakku.test'],
            'books' => [['public_id' => '01m3buku', 'name' => 'Warung Kopi', 'is_default' => true]],
        ]),
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 90000]],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    Livewire::test('login')
        ->set('email', 'budi@rakku.test')
        ->set('password', 'rahasia-123')
        ->call('submit')
        ->assertRedirect(route('home'));

    expect(Account::query()->sole()->name)->toBe('Kas Laci')
        ->and(app(TokenStore::class)->lastSyncedAt())->toBe('2026-10-01T03:00:00Z');
});

it('still lets the user in when that first pull fails', function () {
    Http::fake([
        '*/api/v1/login' => Http::response([
            'token' => 'token-rahasia',
            'user' => ['name' => 'Budi', 'email' => 'budi@rakku.test'],
            'books' => [['public_id' => '01m3buku', 'name' => 'Warung Kopi', 'is_default' => true]],
        ]),
        '*/api/v1/books/*/sync*' => Http::response('', 500),
    ]);

    Livewire::test('login')
        ->set('email', 'budi@rakku.test')
        ->set('password', 'rahasia-123')
        ->call('submit')
        ->assertRedirect(route('home'));

    expect(app(TokenStore::class)->isSignedIn())->toBeTrue();
});

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

it('clears the book off the phone when the user signs out', function () {
    Http::fake(['*/api/v1/logout' => Http::response([])]);
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');
    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 100_000]);
    Debt::query()->create([
        'public_id' => '01m3utang',
        'type' => 'receivable',
        'counterparty_name' => 'Bu Rina',
        'amount' => 100_000,
        'remaining_amount' => 100_000,
    ]);

    Livewire::test('home')->call('signOut')->assertRedirect(route('login'));

    expect(Debt::query()->count())->toBe(0)
        ->and(Account::query()->count())->toBe(0);
});

it('holds the user back from signing out while notes are still waiting', function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');
    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);
    Transaction::query()->create([
        'public_id' => '01m3trx',
        'account_public_id' => '01m3kas',
        'type' => 'expense',
        'amount' => 25_000,
        'transaction_date' => '2026-10-01',
        'is_dirty' => true,
    ]);

    Livewire::test('home')->call('signOut')->assertNoRedirect();

    expect(app(TokenStore::class)->isSignedIn())->toBeTrue()
        ->and(Transaction::query()->count())->toBe(1);
});

it('takes a signed-in phone straight to its book instead of asking again', function () {
    app(TokenStore::class)->rememberSession('token-rahasia', 'Budi');
    app(TokenStore::class)->rememberBook('01m3buku', 'Warung Kopi');

    $this->get('/')->assertRedirect(route('home'));
});

it('keeps the tab bar off the login screen', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('tabs__tab', escape: false);
});
