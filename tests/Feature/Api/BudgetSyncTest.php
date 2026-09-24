<?php

use App\Enums\SubscriptionPlan;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = $this->user->books()->first();
    $this->category = $this->book->categories()->first();

    Sanctum::actingAs($this->user);
});

it('hands the phone every budget on the book', function () {
    $budget = Budget::factory()->for($this->book)->for($this->category)->create(['amount' => 500_000]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('budgets'))->toHaveCount(1)
        ->and($response->json('budgets.0.public_id'))->toBe($budget->public_id)
        ->and($response->json('budgets.0.category_public_id'))->toBe($this->category->public_id)
        ->and((float) $response->json('budgets.0.amount'))->toBe(500_000.0);
});

it('accepts a budget set on the phone even without a subscription', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => $publicId,
            'category_public_id' => $this->category->public_id,
            'amount' => 750_000,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $budget = Budget::query()->where('public_id', $publicId)->sole();

    expect($budget->book_id)->toBe($this->book->id)
        ->and((float) $budget->amount)->toBe(750_000.0)
        ->and($budget->alert_enabled)->toBeFalse();
});

it('leaves the alert settings alone for an account that is not premium', function () {
    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'category_public_id' => $this->category->public_id,
            'amount' => 750_000,
            'alert_enabled' => true,
            'alert_threshold_percent' => 80,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect(Budget::query()->sole()->alert_enabled)->toBeFalse();
});

it('keeps the alert settings for a premium account', function () {
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'category_public_id' => $this->category->public_id,
            'amount' => 750_000,
            'alert_enabled' => true,
            'alert_threshold_percent' => 80,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $budget = Budget::query()->sole();

    expect($budget->alert_enabled)->toBeTrue()
        ->and($budget->alert_threshold_percent)->toBe(80);
});

it('turns down a second budget for a category that already has one', function () {
    Budget::factory()->for($this->book)->for($this->category)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'category_public_id' => $this->category->public_id,
            'amount' => 900_000,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Budget::query()->count())->toBe(1);
});

it('drops a budget the phone says was removed', function () {
    $budget = Budget::factory()->for($this->book)->for($this->category)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => $budget->public_id,
            'category_public_id' => $this->category->public_id,
            'amount' => (float) $budget->amount,
            'is_deleted' => true,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $this->assertModelMissing($budget);
});

it('refuses a budget pointing at a category from another book', function () {
    $foreignCategory = Category::factory()->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'budgets' => [[
            'public_id' => Str::lower((string) Str::ulid()),
            'category_public_id' => $foreignCategory->public_id,
            'amount' => 100_000,
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 0, 'skipped' => 1]);

    expect(Budget::query()->count())->toBe(0);
});
