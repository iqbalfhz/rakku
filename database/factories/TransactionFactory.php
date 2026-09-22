<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'type' => fake()->randomElement(TransactionType::cases()),
            'category_id' => fn (array $attributes) => Category::factory()->state([
                'book_id' => $attributes['book_id'],
                'type' => $attributes['type'],
            ]),
            'amount' => fake()->numberBetween(10, 1_000) * 1_000,
            'description' => fake()->optional()->sentence(),
            'transaction_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
        ]);
    }
}
