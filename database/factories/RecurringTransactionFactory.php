<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'account_id' => fn (array $attributes) => Account::factory()->state(['book_id' => $attributes['book_id']]),
            'type' => TransactionType::Expense,
            'category_id' => fn (array $attributes) => Category::factory()->state([
                'book_id' => $attributes['book_id'],
                'type' => $attributes['type'],
            ]),
            'amount' => fake()->numberBetween(50, 500) * 1_000,
            'description' => fake()->randomElement(['Tagihan listrik', 'Internet', 'Langganan streaming']),
            'frequency' => RecurringFrequency::Monthly,
            'start_date' => today(),
            'end_date' => null,
            'is_active' => true,
        ];
    }
}
