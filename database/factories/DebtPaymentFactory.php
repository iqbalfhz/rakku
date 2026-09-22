<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtPayment>
 */
class DebtPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'debt_id' => Debt::factory(),
            'account_id' => fn (array $attributes) => Account::factory()->state([
                'book_id' => Debt::query()->whereKey($attributes['debt_id'])->value('book_id'),
            ]),
            'amount' => fake()->numberBetween(1, 10) * 10_000,
            'payment_date' => today(),
            'notes' => null,
        ];
    }
}
