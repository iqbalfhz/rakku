<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Book;
use App\Models\Subscription;
use App\Models\User;

it('treats a user as premium only while the latest subscription is an active premium plan', function (array $subscription, bool $isPremium) {
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create($subscription);

    expect($user->fresh()->isPremium())->toBe($isPremium);
})->with([
    'active premium without expiry' => [['plan' => SubscriptionPlan::Premium], true],
    'active premium expiring later' => [fn () => ['plan' => SubscriptionPlan::Premium, 'expires_at' => now()->addMonth()], true],
    'premium already expired' => [fn () => ['plan' => SubscriptionPlan::Premium, 'expires_at' => now()->subMinute()], false],
    'premium cancelled' => [['plan' => SubscriptionPlan::Premium, 'status' => SubscriptionStatus::Cancelled], false],
    'active free plan' => [['plan' => SubscriptionPlan::Free], false],
]);

it('cancels the previous active subscription when switching plans', function () {
    $user = User::factory()->create();
    $freeSubscription = $user->currentSubscription;

    $premiumSubscription = $user->subscribeTo(SubscriptionPlan::Premium, now()->addYear());

    expect($freeSubscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($premiumSubscription->status)->toBe(SubscriptionStatus::Active)
        ->and($user->fresh()->isPremium())->toBeTrue();
});

it('allows free users to own only one book', function () {
    $user = User::factory()->create();

    expect($user->canCreateBook())->toBeFalse();
});

it('allows premium users to create additional books', function () {
    $user = User::factory()->premium()->create();
    Book::factory()->for($user)->create();

    expect($user->canCreateBook())->toBeTrue();
});

it('only grants tenant access to the owner of a book', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $book = $owner->books()->first();

    expect($owner->canAccessTenant($book))->toBeTrue()
        ->and($otherUser->canAccessTenant($book))->toBeFalse();
});
