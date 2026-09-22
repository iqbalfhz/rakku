<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Book;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'client_id' => fn (array $attributes) => Client::factory()->state(['book_id' => $attributes['book_id']]),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####-####'),
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'status' => InvoiceStatus::Draft,
            'notes' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Sent,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Overdue,
            'issue_date' => today()->subDays(30),
            'due_date' => today()->subDays(2),
        ]);
    }
}
