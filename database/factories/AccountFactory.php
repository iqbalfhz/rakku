<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
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
            'name' => fake()->randomElement(['Cash', 'BCA', 'Mandiri', 'GoPay', 'OVO', 'DANA']),
            'type' => fake()->randomElement(AccountType::cases()),
            'initial_balance' => fake()->numberBetween(0, 5_000) * 1_000,
        ];
    }
}
