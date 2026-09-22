<?php

use App\Enums\RecurringFrequency;
use App\Models\Book;
use App\Models\RecurringTransaction;
use App\Models\User;

function recurringFor(User $user, array $attributes = []): RecurringTransaction
{
    return RecurringTransaction::factory()
        ->for(Book::factory()->for($user))
        ->create($attributes);
}

it('generates every missed occurrence and schedules the next run', function () {
    $this->travelTo('2026-03-10');
    $recurring = recurringFor(User::factory()->premium()->create(), [
        'frequency' => RecurringFrequency::Monthly,
        'start_date' => '2026-01-10',
        'amount' => 150_000,
    ]);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    expect($recurring->transactions()->orderBy('transaction_date')->pluck('transaction_date')->map->toDateString()->all())
        ->toBe(['2026-01-10', '2026-02-10', '2026-03-10'])
        ->and($recurring->fresh()->next_run_date->toDateString())->toBe('2026-04-10')
        ->and($recurring->account->fresh()->current_balance)
        ->toBe(number_format((float) $recurring->account->initial_balance - 450_000, 2, '.', ''));
});

it('keeps month-end schedules on the original day after a short month', function () {
    $this->travelTo('2026-03-31');
    $recurring = recurringFor(User::factory()->premium()->create(), [
        'frequency' => RecurringFrequency::Monthly,
        'start_date' => '2026-01-31',
    ]);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    expect($recurring->transactions()->orderBy('transaction_date')->pluck('transaction_date')->map->toDateString()->all())
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('deactivates the schedule after the end date', function () {
    $this->travelTo('2026-01-20');
    $recurring = recurringFor(User::factory()->premium()->create(), [
        'frequency' => RecurringFrequency::Weekly,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-10',
    ]);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    expect($recurring->transactions()->count())->toBe(2)
        ->and($recurring->fresh()->is_active)->toBeFalse();
});

it('skips schedules owned by free users', function () {
    $this->travelTo('2026-01-10');
    $recurring = recurringFor(User::factory()->create(), ['start_date' => '2026-01-01']);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    expect($recurring->transactions()->count())->toBe(0)
        ->and($recurring->fresh()->next_run_date->toDateString())->toBe('2026-01-01');
});
