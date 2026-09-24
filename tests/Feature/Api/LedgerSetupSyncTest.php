<?php

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = $this->user->books()->first();

    Sanctum::actingAs($this->user);
});

it('tells the phone enough about an account to edit it', function () {
    $account = Account::factory()->for($this->book)->create(['initial_balance' => 250_000]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    $payload = collect($response->json('accounts'))->firstWhere('public_id', $account->public_id);

    expect((float) $payload['initial_balance'])->toBe(250_000.0)
        ->and($payload['has_activity'])->toBeFalse()
        ->and($payload['updated_at'])->toEndWith('Z');
});

it('accepts an account opened on the phone', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'accounts' => [[
            'public_id' => $publicId,
            'name' => 'BRI Warung',
            'type' => 'bank',
            'initial_balance' => 500_000,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $account = Account::query()->where('public_id', $publicId)->sole();

    expect($account->book_id)->toBe($this->book->id)
        ->and($account->type)->toBe(AccountType::Bank)
        ->and((float) $account->current_balance)->toBe(500_000.0);
});

it('accepts a category made on the phone', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'categories' => [[
            'public_id' => $publicId,
            'name' => 'Bensin',
            'type' => 'expense',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $category = Category::query()->where('public_id', $publicId)->sole();

    expect($category->book_id)->toBe($this->book->id)
        ->and($category->type)->toBe(TransactionType::Expense);
});

it('takes a transaction that points at an account made in the same push', function () {
    $accountPublicId = Str::lower((string) Str::ulid());
    $categoryPublicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'accounts' => [[
            'public_id' => $accountPublicId,
            'name' => 'Dompet',
            'type' => 'cash',
            'initial_balance' => 0,
            'updated_at' => now()->toIso8601String(),
        ]],
        'categories' => [[
            'public_id' => $categoryPublicId,
            'name' => 'Bensin',
            'type' => 'expense',
            'updated_at' => now()->toIso8601String(),
        ]],
        'transactions' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'account_public_id' => $accountPublicId,
            'category_public_id' => $categoryPublicId,
            'type' => 'expense',
            'amount' => 50_000,
            'transaction_date' => '2026-10-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 3, 'skipped' => 0]);

    $transaction = Transaction::query()->sole();

    expect($transaction->account->public_id)->toBe($accountPublicId)
        ->and($transaction->category->public_id)->toBe($categoryPublicId);
});

it('shifts the running balance when the phone corrects the opening balance', function () {
    $account = Account::factory()->for($this->book)->create(['initial_balance' => 100_000]);
    Transaction::factory()->for($this->book)->for($account)->create(['type' => TransactionType::Expense, 'amount' => 30_000]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'accounts' => [[
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type->value,
            'initial_balance' => 150_000,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect((float) $account->fresh()->current_balance)->toBe(120_000.0);
});

it('refuses to delete an account that already has history', function () {
    $account = Account::factory()->for($this->book)->create();
    Transaction::factory()->for($this->book)->for($account)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'accounts' => [[
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type->value,
            'initial_balance' => (float) $account->initial_balance,
            'is_deleted' => true,
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect($account->fresh())->not->toBeNull();
});

it('lets go of an account that was never used', function () {
    $account = Account::factory()->for($this->book)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'accounts' => [[
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type->value,
            'initial_balance' => (float) $account->initial_balance,
            'is_deleted' => true,
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $this->assertModelMissing($account);
});

it('frees the transactions of a category the phone removed', function () {
    $category = Category::factory()->for($this->book)->create();
    $account = Account::factory()->for($this->book)->create();
    $transaction = Transaction::factory()->for($this->book)->for($account)->for($category)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'categories' => [[
            'public_id' => $category->public_id,
            'name' => $category->name,
            'type' => $category->type->value,
            'is_deleted' => true,
            'updated_at' => now()->addDay()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $this->assertModelMissing($category);
    expect($transaction->fresh()->category_id)->toBeNull();
});

it('lets the newest edit win on an account changed in both places', function () {
    $this->travelTo('2026-10-01 10:00');
    $account = Account::factory()->for($this->book)->create(['name' => 'Versi server']);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'accounts' => [[
            'public_id' => $account->public_id,
            'name' => 'Versi ponsel yang lebih lama',
            'type' => $account->type->value,
            'initial_balance' => (float) $account->initial_balance,
            'updated_at' => now()->subHour()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect($account->fresh()->name)->toBe('Versi server');
});
