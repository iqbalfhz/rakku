<?php

use App\Models\User;
use Illuminate\Support\Str;

it('redirects guests to the login page', function () {
    $this->get('/app')->assertRedirect('/app/login');
});

it('returns 404 when a user opens a book owned by someone else', function () {
    $user = User::factory()->create();
    $otherBook = User::factory()->create()->books()->first();

    $this->actingAs($user)->get("/app/{$otherBook->public_id}")->assertNotFound();
});

it('addresses books by a random public id instead of the sequential database id', function () {
    $user = User::factory()->create();
    $book = $user->books()->first();

    expect(Str::isUlid($book->public_id))->toBeTrue();
    $this->actingAs($user)->get('/app')->assertRedirect("/app/{$book->public_id}");
    $this->actingAs($user)->get("/app/{$book->id}")->assertNotFound();
});

it('opens the dashboard of the user own book', function () {
    $user = User::factory()->create();
    $book = $user->books()->first();

    $this->actingAs($user)->get("/app/{$book->public_id}")->assertSuccessful();
});

it('forbids non-admin users from the admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('lets admins open the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSuccessful();
});

it('forbids free users from premium pages', function (string $path) {
    $user = User::factory()->create();
    $book = $user->books()->first();

    $this->actingAs($user)->get("/app/{$book->public_id}/{$path}")->assertForbidden();
})->with([
    'debts' => 'debts',
    'invoices' => 'invoices',
    'clients' => 'clients',
    'recurring transactions' => 'recurring-transactions',
    'profit and loss report' => 'laporan/laba-rugi',
]);

it('lets premium users open premium pages', function (string $path) {
    $user = User::factory()->premium()->create();
    $book = $user->books()->first();

    $this->actingAs($user)->get("/app/{$book->public_id}/{$path}")->assertSuccessful();
})->with([
    'debts' => 'debts',
    'invoices' => 'invoices',
    'clients' => 'clients',
    'recurring transactions' => 'recurring-transactions',
    'profit and loss report' => 'laporan/laba-rugi',
]);

it('renders every free page for a free user', function (string $path) {
    $user = User::factory()->create();
    $book = $user->books()->first();

    $this->actingAs($user)->get("/app/{$book->public_id}/{$path}")->assertSuccessful();
})->with([
    'accounts' => 'accounts',
    'categories' => 'categories',
    'budgets' => 'budgets',
    'transactions' => 'transactions',
    'transfers' => 'transfers',
    'cash flow report' => 'laporan/cash-flow',
    'book settings' => 'profile',
]);
