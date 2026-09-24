<?php

use App\Enums\RecurringFrequency;
use App\Enums\SubscriptionPlan;
use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    // Setahun, karena sebagian tes di sini melompat ke bulan depan.
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addYear());
    $this->book = $this->user->books()->first();
    $this->account = Account::factory()->for($this->book)->create(['initial_balance' => 0]);

    Sanctum::actingAs($this->user);
});

it('hands the phone every schedule on the book', function () {
    $recurring = RecurringTransaction::factory()->for($this->book)->for($this->account)->create([
        'frequency' => RecurringFrequency::Monthly,
        'amount' => 350_000,
    ]);

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('recurring_transactions'))->toHaveCount(1)
        ->and($response->json('recurring_transactions.0.public_id'))->toBe($recurring->public_id)
        ->and($response->json('recurring_transactions.0.frequency'))->toBe('monthly')
        ->and($response->json('recurring_transactions.0.next_run_date'))->toBe($recurring->next_run_date->toDateString());
});

it('leaves schedules out for an account that is not premium', function () {
    $this->user->currentSubscription->forceFill(['expires_at' => now()->subDay()])->save();
    RecurringTransaction::factory()->for($this->book)->for($this->account)->create();

    $response = $this->getJson("/api/v1/books/{$this->book->public_id}/sync")->assertSuccessful();

    expect($response->json('recurring_transactions'))->toBeEmpty();
});

it('accepts a schedule set up on the phone', function () {
    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'recurring_transactions' => [[
            'public_id' => $publicId,
            'account_public_id' => $this->account->public_id,
            'type' => 'expense',
            'amount' => 350_000,
            'description' => 'Bayar listrik',
            'frequency' => 'monthly',
            'start_date' => '2026-11-05',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertSuccessful()->assertJson(['applied' => 1, 'skipped' => 0]);

    $recurring = RecurringTransaction::query()->where('public_id', $publicId)->sole();

    expect($recurring->description)->toBe('Bayar listrik')
        ->and($recurring->frequency)->toBe(RecurringFrequency::Monthly)
        ->and($recurring->next_run_date->toDateString())->toBe('2026-11-05')
        ->and($recurring->is_active)->toBeTrue();
});

it('never lets the phone drag a running schedule backwards', function () {
    $recurring = RecurringTransaction::factory()->for($this->book)->for($this->account)->create([
        'frequency' => RecurringFrequency::Monthly,
        'start_date' => '2026-01-05',
        'next_run_date' => '2026-11-05',
    ]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'recurring_transactions' => [[
            'public_id' => $recurring->public_id,
            'account_public_id' => $this->account->public_id,
            'type' => $recurring->type->value,
            'amount' => 400_000,
            'frequency' => 'monthly',
            'start_date' => '2026-01-05',
            'next_run_date' => '2026-01-05',
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect($recurring->fresh()->next_run_date->toDateString())->toBe('2026-11-05')
        ->and((float) $recurring->fresh()->amount)->toBe(400_000.0);
});

it('pauses a schedule when the phone switches it off', function () {
    $recurring = RecurringTransaction::factory()->for($this->book)->for($this->account)->create(['is_active' => true]);

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'recurring_transactions' => [[
            'public_id' => $recurring->public_id,
            'account_public_id' => $this->account->public_id,
            'type' => $recurring->type->value,
            'amount' => (float) $recurring->amount,
            'frequency' => $recurring->frequency->value,
            'start_date' => $recurring->start_date->toDateString(),
            'is_active' => false,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    expect($recurring->fresh()->is_active)->toBeFalse();
});

it('drops a schedule the phone says was removed', function () {
    $recurring = RecurringTransaction::factory()->for($this->book)->for($this->account)->create();

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'recurring_transactions' => [[
            'public_id' => $recurring->public_id,
            'account_public_id' => $this->account->public_id,
            'type' => $recurring->type->value,
            'amount' => (float) $recurring->amount,
            'frequency' => $recurring->frequency->value,
            'start_date' => $recurring->start_date->toDateString(),
            'is_deleted' => true,
            'updated_at' => now()->addMinute()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $this->assertModelMissing($recurring);
});

it('catches up the runs a schedule missed while the phone was offline', function () {
    $this->travelTo('2026-11-05');

    $publicId = Str::lower((string) Str::ulid());

    $this->postJson("/api/v1/books/{$this->book->public_id}/sync", [
        'transactions' => [],
        'recurring_transactions' => [[
            'public_id' => $publicId,
            'account_public_id' => $this->account->public_id,
            'type' => 'expense',
            'amount' => 100_000,
            'frequency' => 'daily',
            'start_date' => '2026-11-01',
            'updated_at' => now()->toIso8601String(),
        ]],
    ])->assertJson(['applied' => 1]);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    // Lima hari terlewat saat jadwalnya masih di ponsel, semuanya ikut dibuat.
    expect($this->book->transactions()->count())->toBe(5);
});
