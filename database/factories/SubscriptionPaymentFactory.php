<?php

namespace Database\Factories;

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $package = fake()->randomElement(SubscriptionPackage::cases());

        return [
            'user_id' => User::factory(),
            'package' => $package,
            'amount' => $package->price(),
            'proof_path' => 'payment-proofs/'.fake()->uuid().'.jpg',
            'status' => SubscriptionPaymentStatus::Pending,
            'note' => null,
        ];
    }

    public function package(SubscriptionPackage $package): static
    {
        return $this->state(fn (array $attributes): array => [
            'package' => $package,
            'amount' => $package->price(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionPaymentStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Nominal tidak sesuai.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionPaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }
}
