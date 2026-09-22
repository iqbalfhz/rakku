<?php

namespace Database\Factories;

use App\Enums\DebtType;
use App\Models\Book;
use App\Models\Debt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
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
            'type' => fake()->randomElement(DebtType::cases()),
            'counterparty_name' => fake()->name(),
            'amount' => fake()->numberBetween(10, 100) * 10_000,
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'reminder_enabled' => true,
        ];
    }

    public function receivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DebtType::Receivable,
        ]);
    }

    public function payable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DebtType::Payable,
        ]);
    }
}
