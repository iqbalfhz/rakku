<?php

use App\Enums\TransactionType;
use App\Filament\App\Widgets\BalanceOverview;
use App\Filament\App\Widgets\CashFlowChart;
use App\Filament\App\Widgets\CashFlowOverview;
use App\Filament\App\Widgets\CashFlowTrendChart;
use App\Filament\App\Widgets\CategoryBreakdownTable;
use App\Filament\App\Widgets\CategoryComparisonChart;
use App\Filament\App\Widgets\MonthOverMonthOverview;
use App\Filament\App\Widgets\ProfitLossOverview;
use App\Filament\App\Widgets\TopExpenseCategoriesChart;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

function recordExpense(Book $book, string $category, int $amount): void
{
    Transaction::factory()
        ->for($book)
        ->for(Account::factory()->for($book))
        ->for(Category::factory()->for($book)->expense()->state(['name' => $category]))
        ->expense()
        ->create(['amount' => $amount, 'transaction_date' => today()]);
}

it('summarizes balances and this month cash flow', function () {
    $this->travelTo('2026-09-15');
    $book = actingInBook(User::factory()->create());
    $account = Account::factory()->for($book)->create(['initial_balance' => 1_250_000]);
    Transaction::factory()->for($book)->for($account)->expense()->create([
        'amount' => 250_000,
        'transaction_date' => today(),
    ]);

    Livewire::test(BalanceOverview::class)
        ->assertSee("Rp\u{A0}1.000.000")
        ->assertSee("Rp\u{A0}250.000");
});

it('names the largest expense category as the basic insight', function () {
    $this->travelTo('2026-09-15');
    $book = actingInBook(User::factory()->create());
    recordExpense($book, 'Makan', 300_000);
    recordExpense($book, 'Transportasi', 200_000);

    Livewire::test(TopExpenseCategoriesChart::class)
        ->assertSee('Makan menyerap 60% pengeluaran');
});

it('renders the dashboard and report widgets without errors', function (string $widget, array $properties) {
    $this->travelTo('2026-09-15');
    $book = actingInBook(User::factory()->premium()->create());
    recordExpense($book, 'Makan', 100_000);

    Livewire::test($widget, $properties)->assertOk();
})->with([
    'cash flow trend' => [CashFlowTrendChart::class, []],
    'month over month' => [MonthOverMonthOverview::class, []],
    'category comparison' => [CategoryComparisonChart::class, []],
    'monthly cash flow chart' => [CashFlowChart::class, ['pageFilters' => ['period' => 'monthly', 'year' => 2026, 'month' => 9]]],
    'yearly cash flow chart' => [CashFlowChart::class, ['pageFilters' => ['period' => 'yearly', 'year' => 2026]]],
    'cash flow overview' => [CashFlowOverview::class, ['pageFilters' => ['period' => 'monthly', 'year' => 2026, 'month' => 9]]],
    'profit and loss overview' => [ProfitLossOverview::class, ['pageFilters' => ['period' => 'yearly', 'year' => 2026]]],
    'expense breakdown' => [CategoryBreakdownTable::class, ['transactionType' => TransactionType::Expense->value, 'pageFilters' => ['period' => 'monthly', 'year' => 2026, 'month' => 9]]],
]);

it('lists the profit and loss breakdown per category for the selected period', function () {
    $this->travelTo('2026-09-15');
    $book = actingInBook(User::factory()->premium()->create());
    recordExpense($book, 'Bahan Baku', 400_000);

    Livewire::test(CategoryBreakdownTable::class, [
        'transactionType' => TransactionType::Expense->value,
        'pageFilters' => ['period' => 'monthly', 'year' => 2026, 'month' => 9],
    ])
        ->assertSee('Bahan Baku')
        ->assertSee("Rp\u{A0}400.000,00");
});

it('hides advanced insight widgets from free users', function () {
    actingInBook(User::factory()->create());

    expect(MonthOverMonthOverview::canView())->toBeFalse()
        ->and(CategoryComparisonChart::canView())->toBeFalse();
});
