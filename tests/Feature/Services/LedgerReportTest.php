<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\LedgerReport;
use Carbon\CarbonImmutable;

function recordTransaction(Book $book, TransactionType $type, int $amount, string $date, ?Category $category = null): Transaction
{
    return Transaction::factory()
        ->for($book)
        ->for(Account::factory()->for($book))
        ->state(['category_id' => $category?->id])
        ->create(['type' => $type, 'amount' => $amount, 'transaction_date' => $date]);
}

it('totals income and expenses in the period while ignoring transfers', function () {
    $book = Book::factory()->create();
    recordTransaction($book, TransactionType::Income, 500_000, '2026-04-01');
    recordTransaction($book, TransactionType::Expense, 120_000, '2026-04-30');
    recordTransaction($book, TransactionType::Expense, 999_000, '2026-05-01');
    Transfer::factory()->for($book)->create(['amount' => 300_000, 'transfer_date' => '2026-04-10']);

    $totals = (new LedgerReport($book))->totals(CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-04-30'));

    expect($totals)->toBe(['income' => 500_000.0, 'expense' => 120_000.0, 'net' => 380_000.0]);
});

it('groups the cash flow of a year by month', function () {
    $book = Book::factory()->create();
    recordTransaction($book, TransactionType::Income, 200_000, '2026-01-05');
    recordTransaction($book, TransactionType::Income, 100_000, '2026-01-25');
    recordTransaction($book, TransactionType::Expense, 50_000, '2026-03-15');

    $series = (new LedgerReport($book))->monthlyCashFlow(2026);

    expect($series)->toHaveCount(12)
        ->and($series[0])->toMatchArray(['income' => 300_000.0, 'expense' => 0.0])
        ->and($series[2])->toMatchArray(['income' => 0.0, 'expense' => 50_000.0]);
});

it('groups the cash flow of a month by day', function () {
    $book = Book::factory()->create();
    recordTransaction($book, TransactionType::Expense, 75_000, '2026-02-28');

    $series = (new LedgerReport($book))->dailyCashFlow(CarbonImmutable::parse('2026-02-10'));

    expect($series)->toHaveCount(28)
        ->and($series[27])->toMatchArray(['label' => '28', 'expense' => 75_000.0]);
});

it('ranks expense categories from the largest total', function () {
    $book = Book::factory()->create();
    $food = Category::factory()->for($book)->expense()->create(['name' => 'Makan']);
    $transport = Category::factory()->for($book)->expense()->create(['name' => 'Transportasi']);
    recordTransaction($book, TransactionType::Expense, 40_000, '2026-04-02', $transport);
    recordTransaction($book, TransactionType::Expense, 90_000, '2026-04-03', $food);
    recordTransaction($book, TransactionType::Expense, 10_000, '2026-04-04');

    $ranking = (new LedgerReport($book))->totalsByCategory(
        TransactionType::Expense,
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
    );

    expect($ranking->all())->toBe([
        ['category' => 'Makan', 'total' => 90_000.0],
        ['category' => 'Transportasi', 'total' => 40_000.0],
        ['category' => 'Tanpa Kategori', 'total' => 10_000.0],
    ]);
});

it('compares category spending with the previous month', function () {
    $book = Book::factory()->create();
    $food = Category::factory()->for($book)->expense()->create(['name' => 'Makan']);
    $fun = Category::factory()->for($book)->expense()->create(['name' => 'Hiburan']);
    recordTransaction($book, TransactionType::Expense, 100_000, '2026-03-10', $food);
    recordTransaction($book, TransactionType::Expense, 150_000, '2026-04-10', $food);
    recordTransaction($book, TransactionType::Expense, 20_000, '2026-04-12', $fun);

    $comparison = (new LedgerReport($book))->categoryComparison(TransactionType::Expense, CarbonImmutable::parse('2026-04-15'));

    expect($comparison->all())->toBe([
        ['category' => 'Makan', 'current' => 150_000.0, 'previous' => 100_000.0, 'change_percent' => 50.0],
        ['category' => 'Hiburan', 'current' => 20_000.0, 'previous' => 0.0, 'change_percent' => null],
    ]);
});
