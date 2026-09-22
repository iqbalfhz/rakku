<?php

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Filament\App\Resources\RecurringTransactions\Pages\ManageRecurringTransactions;
use App\Models\Account;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

it('generates the occurrences already due as soon as the schedule is created', function () {
    $this->travelTo('2026-09-20');
    $book = actingInBook(User::factory()->premium()->create());
    $account = Account::factory()->for($book)->create();

    Livewire::test(ManageRecurringTransactions::class)
        ->callAction(CreateAction::class, data: [
            'type' => TransactionType::Income->value,
            'frequency' => RecurringFrequency::Weekly->value,
            'account_id' => $account->id,
            'amount' => 100_000,
            'description' => 'Uang saku',
            'start_date' => '2026-09-06',
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $recurring = $book->recurringTransactions()->sole();

    expect($recurring->transactions()->count())->toBe(3)
        ->and($recurring->next_run_date->toDateString())->toBe('2026-09-27');
});
