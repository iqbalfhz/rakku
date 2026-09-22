<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
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
            'category_id' => fn (array $attributes) => Category::factory()->expense()->state(['book_id' => $attributes['book_id']]),
            'amount' => fake()->numberBetween(5, 50) * 100_000,
            'alert_enabled' => false,
            'alert_threshold_percent' => null,
        ];
    }

    public function withAlert(int $thresholdPercent = 80): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_enabled' => true,
            'alert_threshold_percent' => $thresholdPercent,
        ]);
    }
}
