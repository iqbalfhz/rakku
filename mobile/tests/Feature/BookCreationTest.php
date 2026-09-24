<?php

use App\Models\Account;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
});

/**
 * Jawaban server saat buku baru berhasil dibuat.
 *
 * @param  list<array{public_id: string, name: string, is_default: bool}>  $books
 */
function fakeBookCreated(array $books): void
{
    Http::fake([
        '*/api/v1/books' => Http::response([
            'book' => $books[count($books) - 1],
            'books' => $books,
        ], 201),
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);
}

it('opens the first book straight away when the phone holds none', function () {
    fakeBookCreated([['public_id' => '01m3buku', 'name' => 'Warung Kopi', 'is_default' => true]]);

    Livewire::test('books')
        ->call('startAdding')
        ->set('newBookName', 'Warung Kopi')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect($this->tokenStore->bookPublicId())->toBe('01m3buku')
        ->and($this->tokenStore->bookName())->toBe('Warung Kopi');
});

it('leaves the open book alone when another one is added', function () {
    $this->tokenStore->rememberBook('01m3lama', 'Pribadi');
    fakeBookCreated([
        ['public_id' => '01m3lama', 'name' => 'Pribadi', 'is_default' => true],
        ['public_id' => '01m3baru', 'name' => 'Warung Kopi', 'is_default' => false],
    ]);

    Livewire::test('books')
        ->call('startAdding')
        ->set('newBookName', 'Warung Kopi')
        ->call('create')
        ->assertHasNoErrors()
        ->assertNoRedirect();

    expect($this->tokenStore->bookPublicId())->toBe('01m3lama')
        ->and(collect($this->tokenStore->books())->pluck('name'))->toContain('Warung Kopi');
});

it('insists on a name', function () {
    Livewire::test('books')
        ->call('startAdding')
        ->call('create')
        ->assertHasErrors('newBookName');

    Http::assertNothingSent();
});

it('passes on the reason when the server says no', function () {
    Http::fake([
        '*/api/v1/books' => Http::response(['message' => 'Buku tambahan hanya untuk pelanggan premium.'], 403),
    ]);

    $this->tokenStore->rememberBook('01m3lama', 'Pribadi');

    Livewire::test('books')
        ->call('startAdding')
        ->set('newBookName', 'Warung Kopi')
        ->call('create')
        ->assertSet('error', 'Buku tambahan hanya untuk pelanggan premium.');

    expect(collect($this->tokenStore->books())->pluck('name'))->not->toContain('Warung Kopi');
});

it('says so plainly when the server cannot be reached', function () {
    Http::fake(['*/api/v1/books' => Http::response('', 500)]);

    Livewire::test('books')
        ->call('startAdding')
        ->set('newBookName', 'Warung Kopi')
        ->call('create')
        ->assertSet('error', 'Buku gagal dibuat. Coba lagi saat sinyal membaik.');
});

it('sends a signed-in account with no book to make one instead of to the browser', function () {
    Http::fake([
        '*/api/v1/login' => Http::response([
            'token' => 'token-rahasia',
            'user' => ['name' => 'Budi', 'email' => 'budi@contoh.test'],
            'books' => [],
        ]),
    ]);

    $this->tokenStore->forget();

    Livewire::test('login')
        ->set('email', 'budi@contoh.test')
        ->set('password', 'rahasia')
        ->call('submit')
        ->assertRedirect(route('books'));

    expect($this->tokenStore->isSignedIn())->toBeTrue()
        ->and($this->tokenStore->bookPublicId())->toBeNull();
});

it('keeps the tab bar away until a book is open', function () {
    $this->get(route('books'))->assertSuccessful()->assertDontSee('tabs__tab', escape: false);

    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas', 'type' => 'cash', 'current_balance' => 0]);

    $this->get(route('books'))->assertSuccessful()->assertSee('tabs__tab', escape: false);
});
