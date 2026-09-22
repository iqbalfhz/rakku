<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;

function budgetForUser(User $user, int $thresholdPercent = 80): Budget
{
    return Budget::factory()
        ->for(Book::factory()->for($user))
        ->withAlert($thresholdPercent)
        ->create(['amount' => 1_000_000]);
}

function spendOn(Budget $budget, int $amount): Transaction
{
    return Transaction::factory()
        ->for($budget->book)
        ->for(Account::factory()->for($budget->book))
        ->for($budget->category)
        ->expense()
        ->create(['amount' => $amount, 'transaction_date' => today()]);
}

it('warns a premium user when spending crosses the alert threshold', function () {
    $user = User::factory()->premium()->create();
    $budget = budgetForUser($user);
    spendOn($budget, 700_000);

    spendOn($budget, 150_000);

    expect($user->notifications)->toHaveCount(1)
        ->and($user->notifications->first()->data['title'])->toBe("Budget {$budget->category->name} hampir habis");
});

it('alerts a premium user when spending exceeds the limit', function () {
    $user = User::factory()->premium()->create();
    $budget = budgetForUser($user);
    spendOn($budget, 850_000);
    $user->notifications()->delete();

    spendOn($budget, 200_000);

    expect($user->notifications)->toHaveCount(1)
        ->and($user->notifications->first()->data['title'])->toBe("Budget {$budget->category->name} terlampaui");
});

it('does not notify again while spending stays above the threshold', function () {
    $user = User::factory()->premium()->create();
    $budget = budgetForUser($user);
    spendOn($budget, 850_000);
    $user->notifications()->delete();

    spendOn($budget, 50_000);

    expect($user->notifications()->count())->toBe(0);
});

it('does not send budget alerts to free users', function () {
    $user = User::factory()->create();
    $budget = budgetForUser($user);

    spendOn($budget, 1_200_000);

    expect($user->notifications()->count())->toBe(0);
});
