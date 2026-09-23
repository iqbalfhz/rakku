<?php

namespace Database\Factories;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'status' => SupportTicketStatus::Open,
            'last_message_at' => now(),
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupportTicketStatus::Answered,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupportTicketStatus::Closed,
        ]);
    }
}
