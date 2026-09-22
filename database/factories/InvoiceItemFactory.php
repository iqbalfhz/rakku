<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(['Jasa desain', 'Fotocopy A4', 'Jilid spiral', 'Cetak banner']),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(5, 100) * 1_000,
        ];
    }
}
