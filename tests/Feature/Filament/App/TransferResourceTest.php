<?php

use App\Filament\App\Resources\Transfers\Pages\ManageTransfers;
use App\Models\Account;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

it('records a transfer between two accounts of the book', function () {
    $book = actingInBook(User::factory()->create());
    $bank = Account::factory()->for($book)->create(['initial_balance' => 500_000]);
    $wallet = Account::factory()->for($book)->create(['initial_balance' => 0]);

    Livewire::test(ManageTransfers::class)
        ->callAction(CreateAction::class, data: [
            'from_account_id' => $bank->id,
            'to_account_id' => $wallet->id,
            'amount' => 200_000,
            'transfer_date' => '2026-09-10',
        ])
        ->assertHasNoFormErrors();

    expect($bank->fresh()->current_balance)->toBe('300000.00')
        ->and($wallet->fresh()->current_balance)->toBe('200000.00');
});

it('rejects a transfer to the same account', function () {
    $book = actingInBook(User::factory()->create());
    $bank = Account::factory()->for($book)->create();

    Livewire::test(ManageTransfers::class)
        ->callAction(CreateAction::class, data: [
            'from_account_id' => $bank->id,
            'to_account_id' => $bank->id,
            'amount' => 200_000,
            'transfer_date' => '2026-09-10',
        ])
        ->assertHasFormErrors(['to_account_id' => 'different']);

    expect($book->transfers()->count())->toBe(0);
});
