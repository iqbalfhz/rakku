<?php

use App\Models\Account;
use App\Models\Transaction;

it('starts the current balance from the initial balance', function () {
    $account = Account::factory()->create(['initial_balance' => 250_000]);

    expect($account->fresh()->current_balance)->toBe('250000.00');
});

it('shifts the current balance by the difference when the initial balance changes', function () {
    $account = Account::factory()->create(['initial_balance' => 100_000]);
    Transaction::factory()->for($account->book)->for($account)->income()->create(['amount' => 50_000]);

    $account->update(['initial_balance' => 120_000]);

    expect($account->fresh()->current_balance)->toBe('170000.00');
});
