<?php

use App\Enums\TransactionType;
use App\Filament\App\Resources\Budgets\Pages\ManageBudgets;
use App\Models\Budget;
use App\Models\User;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

it('rejects a second budget for the same category', function () {
    $book = actingInBook(User::factory()->create());
    $category = $book->categories()->ofType(TransactionType::Expense)->first();
    Budget::factory()->for($book)->for($category)->create();

    Livewire::test(ManageBudgets::class)
        ->callAction(CreateAction::class, data: [
            'category_id' => $category->id,
            'amount' => 500_000,
        ])
        ->assertHasFormErrors(['category_id' => 'Kategori ini sudah punya budget.']);
});

it('ignores alert settings submitted by free users', function () {
    $book = actingInBook(User::factory()->create());
    $category = $book->categories()->ofType(TransactionType::Expense)->first();

    Livewire::test(ManageBudgets::class)
        ->callAction(CreateAction::class, data: [
            'category_id' => $category->id,
            'amount' => 500_000,
            'alert_enabled' => true,
            'alert_threshold_percent' => 50,
        ])
        ->assertHasNoFormErrors();

    expect($book->budgets()->sole())
        ->alert_enabled->toBeFalse()
        ->alert_threshold_percent->toBeNull();
});

it('saves alert settings for premium users', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $category = $book->categories()->ofType(TransactionType::Expense)->first();

    Livewire::test(ManageBudgets::class)
        ->callAction(CreateAction::class, data: [
            'category_id' => $category->id,
            'amount' => 500_000,
            'alert_enabled' => true,
            'alert_threshold_percent' => 75,
        ])
        ->assertHasNoFormErrors();

    expect($book->budgets()->sole())
        ->alert_enabled->toBeTrue()
        ->alert_threshold_percent->toBe(75);
});
