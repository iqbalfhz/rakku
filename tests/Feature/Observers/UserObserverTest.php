<?php

use App\Enums\SubscriptionPlan;
use App\Enums\TransactionType;
use App\Models\User;

it('gives a new user an active free subscription', function () {
    $user = User::factory()->create();

    expect($user->subscriptions)->toHaveCount(1)
        ->and($user->currentSubscription->plan)->toBe(SubscriptionPlan::Free)
        ->and($user->isPremium())->toBeFalse();
});

it('creates a default book with default categories for a new user', function () {
    $user = User::factory()->create();

    $book = $user->books()->sole();

    expect($book->name)->toBe('Pribadi')
        ->and($book->is_default)->toBeTrue()
        ->and($book->categories()->ofType(TransactionType::Income)->pluck('name')->all())
        ->toBe(['Gaji', 'Bonus', 'Penjualan', 'Pemasukan Lainnya'])
        ->and($book->categories()->ofType(TransactionType::Expense)->count())->toBe(8);
});
