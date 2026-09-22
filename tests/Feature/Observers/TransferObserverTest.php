<?php

use App\Models\Account;
use App\Models\Transfer;

it('moves the amount from the source account to the destination account', function () {
    $cash = Account::factory()->create(['initial_balance' => 100_000]);
    $bank = Account::factory()->for($cash->book)->create(['initial_balance' => 0]);

    Transfer::factory()->for($cash->book)->create([
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'amount' => 60_000,
    ]);

    expect($cash->fresh()->current_balance)->toBe('40000.00')
        ->and($bank->fresh()->current_balance)->toBe('60000.00');
});

it('reapplies balances when a transfer is edited', function () {
    $cash = Account::factory()->create(['initial_balance' => 100_000]);
    $bank = Account::factory()->for($cash->book)->create(['initial_balance' => 0]);
    $wallet = Account::factory()->for($cash->book)->create(['initial_balance' => 0]);
    $transfer = Transfer::factory()->for($cash->book)->create([
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'amount' => 60_000,
    ]);

    $transfer->update(['to_account_id' => $wallet->id, 'amount' => 25_000]);

    expect($cash->fresh()->current_balance)->toBe('75000.00')
        ->and($bank->fresh()->current_balance)->toBe('0.00')
        ->and($wallet->fresh()->current_balance)->toBe('25000.00');
});

it('reverts both balances when a transfer is deleted', function () {
    $cash = Account::factory()->create(['initial_balance' => 100_000]);
    $bank = Account::factory()->for($cash->book)->create(['initial_balance' => 0]);
    $transfer = Transfer::factory()->for($cash->book)->create([
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'amount' => 60_000,
    ]);

    $transfer->delete();

    expect($cash->fresh()->current_balance)->toBe('100000.00')
        ->and($bank->fresh()->current_balance)->toBe('0.00');
});
