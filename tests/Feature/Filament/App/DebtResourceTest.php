<?php

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Filament\App\Resources\Debts\Pages\CreateDebt;
use App\Filament\App\Resources\Debts\Pages\EditDebt;
use App\Filament\App\Resources\Debts\Pages\ListDebts;
use App\Filament\App\Resources\Debts\RelationManagers\PaymentsRelationManager;
use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('creates a debt with the full amount remaining', function () {
    $book = actingInBook(User::factory()->premium()->create());

    Livewire::test(CreateDebt::class)
        ->fillForm([
            'type' => DebtType::Payable->value,
            'counterparty_name' => 'Koperasi',
            'amount' => 2_000_000,
            'due_date' => '2026-10-01',
            'reminder_enabled' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($book->debts()->sole())
        ->remaining_amount->toBe('2000000.00')
        ->status->toBe(DebtStatus::Unpaid);
});

it('renders the edit page with the installment history', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $debt = Debt::factory()->for($book)->create(['amount' => 100_000]);
    $payment = DebtPayment::factory()->for($debt)->for(Account::factory()->for($book))->create(['amount' => 40_000]);

    Livewire::test(EditDebt::class, ['record' => $debt->getRouteKey()])
        ->assertSeeLivewire(PaymentsRelationManager::class);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $debt, 'pageClass' => EditDebt::class])
        ->assertCanSeeTableRecords([$payment])
        ->assertSee("Sisa: Rp\u{A0}60.000");
});

it('records an installment and generates its transaction', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $debt = Debt::factory()->for($book)->receivable()->create(['amount' => 500_000]);
    $account = Account::factory()->for($book)->create(['initial_balance' => 0]);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $debt, 'pageClass' => EditDebt::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), data: [
            'account_id' => $account->id,
            'amount' => 200_000,
            'payment_date' => '2026-09-15',
        ])
        ->assertHasNoFormErrors();

    expect($debt->fresh()->remaining_amount)->toBe('300000.00')
        ->and($debt->payments()->sole()->transaction)->not->toBeNull()
        ->and($account->fresh()->current_balance)->toBe('200000.00');
});

it('rejects an installment larger than the remaining amount', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $debt = Debt::factory()->for($book)->create(['amount' => 100_000]);
    $account = Account::factory()->for($book)->create();

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $debt, 'pageClass' => EditDebt::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), data: [
            'account_id' => $account->id,
            'amount' => 150_000,
            'payment_date' => '2026-09-15',
        ])
        ->assertHasFormErrors(['amount' => 'max']);

    expect($debt->payments()->count())->toBe(0);
});

it('refuses to delete a debt that already has installments', function () {
    $book = actingInBook(User::factory()->premium()->create());
    $debt = Debt::factory()->for($book)->create(['amount' => 100_000]);
    DebtPayment::factory()->for($debt)->for(Account::factory()->for($book))->create(['amount' => 50_000]);

    Livewire::test(ListDebts::class)
        ->callAction(TestAction::make('delete')->table($debt))
        ->assertNotified('Utang-piutang tidak bisa dihapus');

    $this->assertModelExists($debt);
});
