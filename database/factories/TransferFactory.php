<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Book;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
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
            'from_account_id' => fn (array $attributes) => Account::factory()->state(['book_id' => $attributes['book_id']]),
            'to_account_id' => fn (array $attributes) => Account::factory()->state(['book_id' => $attributes['book_id']]),
            'amount' => fake()->numberBetween(10, 500) * 1_000,
            'description' => fake()->optional()->sentence(),
            'transfer_date' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
        ];
    }
}
