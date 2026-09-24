<?php

use App\Enums\SubscriptionPlan;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();

    Sanctum::actingAs($this->user);
});

it('lists the books the phone is allowed to open', function () {
    $response = $this->getJson('/api/v1/books')->assertSuccessful();

    expect($response->json('books'))->toHaveCount(1)
        ->and($response->json('books.0.public_id'))->toBe($this->user->books()->first()->public_id);
});

it('opens a new book for a premium account and hands back the whole list', function () {
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());

    $response = $this->postJson('/api/v1/books', ['name' => 'Warung Kopi'])
        ->assertCreated();

    $book = Book::query()->where('name', 'Warung Kopi')->sole();

    expect($book->user_id)->toBe($this->user->id)
        ->and($response->json('book.public_id'))->toBe($book->public_id)
        ->and($response->json('books'))->toHaveCount(2);
});

it('fills a new book with the usual categories so it is usable right away', function () {
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());

    $this->postJson('/api/v1/books', ['name' => 'Warung Kopi'])->assertCreated();

    $book = Book::query()->where('name', 'Warung Kopi')->sole();

    expect($book->categories()->count())->toBe(
        count(Book::DEFAULT_CATEGORIES['income']) + count(Book::DEFAULT_CATEGORIES['expense'])
    )->and(Category::query()->where('book_id', $book->id)->where('name', 'Transportasi')->exists())->toBeTrue();
});

it('turns down a second book on a free account', function () {
    $this->postJson('/api/v1/books', ['name' => 'Warung Kopi'])
        ->assertForbidden();

    expect($this->user->books()->count())->toBe(1);
});

it('lets a free account that has no book at all make its first one', function () {
    $this->user->books()->delete();

    $this->postJson('/api/v1/books', ['name' => 'Pribadi'])->assertCreated();

    expect($this->user->books()->count())->toBe(1);
});

it('insists on a name', function () {
    $this->postJson('/api/v1/books', ['name' => ''])->assertJsonValidationErrorFor('name');
});

it('turns away a phone without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/books', ['name' => 'Warung Kopi'])->assertUnauthorized();
});

it('never shows one account the books of another', function () {
    $stranger = User::factory()->create();

    $response = $this->getJson('/api/v1/books')->assertSuccessful();

    expect(collect($response->json('books'))->pluck('public_id'))
        ->not->toContain($stranger->books()->first()->public_id);
});
