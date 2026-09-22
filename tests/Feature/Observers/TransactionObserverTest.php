<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('adds income and subtracts expenses from the account balance', function () {
    $account = Account::factory()->create(['initial_balance' => 100_000]);

    Transaction::factory()->for($account->book)->for($account)->income()->create(['amount' => 40_000]);
    Transaction::factory()->for($account->book)->for($account)->expense()->create(['amount' => 15_000]);

    expect($account->fresh()->current_balance)->toBe('125000.00');
});

it('recalculates balances when a transaction moves to another account with a new amount and type', function () {
    $cash = Account::factory()->create(['initial_balance' => 100_000]);
    $bank = Account::factory()->for($cash->book)->create(['initial_balance' => 0]);
    $transaction = Transaction::factory()->for($cash->book)->for($cash)->expense()->create(['amount' => 30_000]);

    $transaction->update([
        'account_id' => $bank->id,
        'type' => TransactionType::Income,
        'amount' => 20_000,
    ]);

    expect($cash->fresh()->current_balance)->toBe('100000.00')
        ->and($bank->fresh()->current_balance)->toBe('20000.00');
});

it('restores the account balance when a transaction is deleted', function () {
    $account = Account::factory()->create(['initial_balance' => 100_000]);
    $transaction = Transaction::factory()->for($account->book)->for($account)->expense()->create(['amount' => 30_000]);

    $transaction->delete();

    expect($account->fresh()->current_balance)->toBe('100000.00');
});

it('deletes the receipt photo together with the transaction', function () {
    Storage::fake(Transaction::RECEIPT_DISK);
    $path = UploadedFile::fake()->image('struk.jpg')->store(Transaction::RECEIPT_DIRECTORY, Transaction::RECEIPT_DISK);
    $transaction = Transaction::factory()->create(['receipt_photo_path' => $path]);

    $transaction->delete();

    Storage::disk(Transaction::RECEIPT_DISK)->assertMissing($path);
});

it('deletes the old receipt photo when it is replaced', function () {
    Storage::fake(Transaction::RECEIPT_DISK);
    $oldPath = UploadedFile::fake()->image('lama.jpg')->store(Transaction::RECEIPT_DIRECTORY, Transaction::RECEIPT_DISK);
    $newPath = UploadedFile::fake()->image('baru.jpg')->store(Transaction::RECEIPT_DIRECTORY, Transaction::RECEIPT_DISK);
    $transaction = Transaction::factory()->create(['receipt_photo_path' => $oldPath]);

    $transaction->update(['receipt_photo_path' => $newPath]);

    Storage::disk(Transaction::RECEIPT_DISK)->assertMissing($oldPath);
    Storage::disk(Transaction::RECEIPT_DISK)->assertExists($newPath);
});
