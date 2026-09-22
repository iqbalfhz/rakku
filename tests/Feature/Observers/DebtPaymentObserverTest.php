<?php

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;

it('records a receivable installment as income and reduces the remaining amount', function () {
    $debt = Debt::factory()->receivable()->create(['amount' => 300_000]);
    $account = Account::factory()->for($debt->book)->create(['initial_balance' => 0]);

    $payment = DebtPayment::factory()->for($debt)->for($account)->create(['amount' => 100_000]);

    expect($payment->transaction)
        ->type->toBe(TransactionType::Income)
        ->amount->toBe('100000.00')
        ->book_id->toBe($debt->book_id)
        ->and($debt->fresh())
        ->remaining_amount->toBe('200000.00')
        ->status->toBe(DebtStatus::Unpaid)
        ->and($account->fresh()->current_balance)->toBe('100000.00');
});

it('records a payable installment as an expense', function () {
    $debt = Debt::factory()->payable()->create(['amount' => 300_000]);
    $account = Account::factory()->for($debt->book)->create(['initial_balance' => 500_000]);

    $payment = DebtPayment::factory()->for($debt)->for($account)->create(['amount' => 100_000]);

    expect($payment->transaction->type)->toBe(TransactionType::Expense)
        ->and($account->fresh()->current_balance)->toBe('400000.00');
});

it('marks the debt as paid once installments cover the full amount', function () {
    $debt = Debt::factory()->receivable()->create(['amount' => 300_000]);
    $account = Account::factory()->for($debt->book)->create();

    DebtPayment::factory()->count(2)->for($debt)->for($account)->create(['amount' => 150_000]);

    expect($debt->fresh())
        ->remaining_amount->toBe('0.00')
        ->status->toBe(DebtStatus::Paid);
});

it('removes the generated transaction and reopens the debt when an installment is deleted', function () {
    $debt = Debt::factory()->receivable()->create(['amount' => 100_000]);
    $account = Account::factory()->for($debt->book)->create(['initial_balance' => 0]);
    $payment = DebtPayment::factory()->for($debt)->for($account)->create(['amount' => 100_000]);
    $transaction = $payment->transaction;

    $payment->delete();

    $this->assertModelMissing($transaction);
    expect($debt->fresh())
        ->remaining_amount->toBe('100000.00')
        ->status->toBe(DebtStatus::Unpaid)
        ->and($account->fresh()->current_balance)->toBe('0.00');
});

it('recalculates the remaining amount when the debt amount is corrected', function () {
    $debt = Debt::factory()->receivable()->create(['amount' => 300_000]);
    DebtPayment::factory()->for($debt)->create(['amount' => 100_000]);

    $debt->fresh()->update(['amount' => 250_000]);

    expect($debt->fresh()->remaining_amount)->toBe('150000.00');
});
