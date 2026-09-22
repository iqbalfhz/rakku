<?php

use App\Enums\AccountType;
use App\Filament\App\Resources\Accounts\Pages\ManageAccounts;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('creates an account in the current book with the initial balance as current balance', function () {
    $book = actingInBook(User::factory()->create());

    Livewire::test(ManageAccounts::class)
        ->callAction(CreateAction::class, data: [
            'name' => 'BCA',
            'type' => AccountType::Bank->value,
            'initial_balance' => 1_250_000,
        ])
        ->assertHasNoFormErrors();

    expect($book->accounts()->sole())
        ->name->toBe('BCA')
        ->current_balance->toBe('1250000.00');
});

it('refuses to delete an account that already has transactions', function () {
    $book = actingInBook(User::factory()->create());
    $account = Account::factory()->for($book)->create();
    Transaction::factory()->for($book)->for($account)->create();

    Livewire::test(ManageAccounts::class)
        ->callAction(TestAction::make('delete')->table($account))
        ->assertNotified('Akun tidak bisa dihapus');

    $this->assertModelExists($account);
});

it('deletes an account without any history', function () {
    $book = actingInBook(User::factory()->create());
    $account = Account::factory()->for($book)->create();

    Livewire::test(ManageAccounts::class)
        ->callAction(TestAction::make('delete')->table($account));

    $this->assertModelMissing($account);
});
