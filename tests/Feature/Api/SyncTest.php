<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = $this->user->books()->first();
    $this->account = Account::factory()->for($this->book)->create(['initial_balance' => 0]);
    $this->category = $this->book->categories()->first();

    Sanctum::actingAs($this->user);
});

it('hands the phone a full picture on the first pull', function () {
    $transaction = Transaction::factory()->for($this->book)->for($this->account)->create([
        'amount' => 50_000,
        'transaction_date' => today(),
    ]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('accounts'))->toHaveCount(1)
        ->and($response->json('accounts.0.public_id'))->toBe($this->account->public_id)
        ->and($response->json('categories'))->not->toBeEmpty()
        ->and($response->json('transactions'))->toHaveCount(1)
        ->and($response->json('transactions.0.public_id'))->toBe($transaction->public_id)
        ->and($response->json('server_time'))->not->toBeNull();
});

it('sends only what changed after the first pull', function () {
    $this->travelTo('2026-10-01 08:00');
    Transaction::factory()->for($this->book)->for($this->account)->create();
    $since = now()->utc()->toIso8601ZuluString();

    $this->travelTo('2026-10-01 09:00');
    $newTransaction = Transaction::factory()->for($this->book)->for($this->account)->create();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync?since={$since}")->assertSuccessful();

    expect($response->json('transactions'))->toHaveCount(1)
        ->and($response->json('transactions.0.public_id'))->toBe($newTransaction->public_id);
});

it('passes deletions on as tombstones so the phone can drop them', function () {
    $this->travelTo('2026-10-01 08:00');
    $transaction = Transaction::factory()->for($this->book)->for($this->account)->create();
    $since = now()->utc()->toIso8601ZuluString();

    $this->travelTo('2026-10-01 09:00');
    $transaction->delete();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync?since={$since}")->assertSuccessful();

    expect($response->json('transactions.0.public_id'))->toBe($transaction->public_id)
        ->and($response->json('transactions.0.is_deleted'))->toBeTrue();
});

it('accepts a transaction recorded offline, keeping the id the phone made', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [[
            'public_id' => $publicId,
            'account_public_id' => $this->account->public_id,
            'category_public_id' => $this->category->public_id,
            'type' => 'expense',
            'amount' => 25_000,
            'description' => 'Beli kertas',
            'transaction_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $transaction = Transaction::query()->where('public_id', $publicId)->sole();

    expect($transaction->book_id)->toBe($this->book->id)
        ->and($transaction->description)->toBe('Beli kertas')
        ->and((float) $transaction->amount)->toBe(25_000.0)
        ->and($this->account->fresh()->current_balance)->toBe('-25000.00');
});

it('lets the newest edit win when both sides changed the same row', function () {
    $this->travelTo('2026-10-01 10:00');
    $transaction = Transaction::factory()->for($this->book)->for($this->account)->create(['description' => 'Versi server']);

    $stalePush = [
        'public_id' => $transaction->public_id,
        'account_public_id' => $this->account->public_id,
        'type' => $transaction->type->value,
        'amount' => (float) $transaction->amount,
        'description' => 'Versi ponsel yang lebih lama',
        'transaction_date' => $transaction->transaction_date->toDateString(),
        'updated_at' => now()->subHour()->toIso8601String(),
    ];

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [$stalePush]])
        ->assertJson(['applied' => 0, 'skipped' => 1]);

    expect($transaction->fresh()->description)->toBe('Versi server');

    $freshPush = [...$stalePush, 'description' => 'Versi ponsel yang lebih baru', 'updated_at' => now()->addHour()->toIso8601String()];

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", ['transactions' => [$freshPush]])
        ->assertJson(['applied' => 1]);

    expect($transaction->fresh()->description)->toBe('Versi ponsel yang lebih baru');
});

it('never resurrects a transaction that was deleted on the server', function () {
    $transaction = Transaction::factory()->for($this->book)->for($this->account)->create();
    $transaction->delete();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [[
            'public_id' => $transaction->public_id,
            'account_public_id' => $this->account->public_id,
            'type' => 'expense',
            'amount' => 10_000,
            'transaction_date' => '2026-10-01',
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Transaction::query()->where('public_id', $transaction->public_id)->exists())->toBeFalse();
});

it('deletes on the server when the phone reports a deletion', function () {
    $transaction = Transaction::factory()->for($this->book)->for($this->account)->create(['amount' => 40_000]);
    $balanceBefore = (float) $this->account->fresh()->current_balance;

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [[
            'public_id' => $transaction->public_id,
            'account_public_id' => $this->account->public_id,
            'type' => $transaction->type->value,
            'amount' => (float) $transaction->amount,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'is_deleted' => true,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect($transaction->fresh()->trashed())->toBeTrue()
        ->and((float) $this->account->fresh()->current_balance)->not->toBe($balanceBefore);
});

it('refuses a transaction pointing at an account from another book', function () {
    $foreignAccount = Account::factory()->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'account_public_id' => $foreignAccount->public_id,
            'type' => 'expense',
            'amount' => 10_000,
            'transaction_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Transaction::query()->count())->toBe(0);
});

it('hides books that belong to someone else', function () {
    $foreignBook = User::factory()->create()->books()->first();

    $this->getJson("/api/v1/books/{$foreignBook->public_id}/sync")->assertNotFound();
    $this->postJson("/api/v1/books/{$foreignBook->public_id}/sync", ['transactions' => []])->assertNotFound();
});

it('turns away requests without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertUnauthorized();
});

it('gives categories with a stable id the phone can reference', function () {
    $category = Category::factory()->for($this->book)->create();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync");

    expect(collect($response->json('categories'))->pluck('public_id'))->toContain($category->public_id);
});
