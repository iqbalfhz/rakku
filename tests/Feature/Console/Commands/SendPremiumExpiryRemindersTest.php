<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Notifications\PremiumExpired;
use App\Notifications\PremiumExpiring;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-10-01');
});

it('reminds a premium user before the subscription runs out', function (int $daysLeft) {
    $user = User::factory()->create();
    $user->subscribeTo(SubscriptionPlan::Premium, now()->addDays($daysLeft)->endOfDay());

    $this->artisan('app:send-premium-expiry-reminders')->assertSuccessful();

    Notification::assertSentTo(
        $user,
        PremiumExpiring::class,
        fn (PremiumExpiring $notification, array $channels): bool => $notification->daysLeft === $daysLeft
            && $channels === ['database', 'mail'],
    );
})->with([
    'seminggu sebelum' => 7,
    'sehari sebelum' => 1,
    'hari terakhir' => 0,
]);

it('stays quiet on days that are not a reminder day', function (int $daysLeft) {
    $user = User::factory()->create();
    $user->subscribeTo(SubscriptionPlan::Premium, now()->addDays($daysLeft)->endOfDay());

    $this->artisan('app:send-premium-expiry-reminders')->assertSuccessful();

    Notification::assertNothingSentTo($user);
})->with([
    'sepuluh hari lagi' => 10,
    'tiga hari lagi' => 3,
]);

it('explains the lockout the day after premium ends, and closes the subscription', function () {
    $user = User::factory()->create();
    $user->subscribeTo(SubscriptionPlan::Premium, now()->subDay()->endOfDay());

    $this->artisan('app:send-premium-expiry-reminders')
        ->expectsOutputToContain('1 premium ditandai berakhir')
        ->assertSuccessful();

    Notification::assertSentTo(
        $user,
        PremiumExpired::class,
        fn (PremiumExpired $notification, array $channels): bool => $channels === ['database', 'mail'],
    );

    expect($user->fresh()->currentSubscription->status)->toBe(SubscriptionStatus::Expired);
});

it('only announces the end once', function () {
    $user = User::factory()->create();
    $user->subscribeTo(SubscriptionPlan::Premium, now()->subDay()->endOfDay());

    $this->artisan('app:send-premium-expiry-reminders');
    $this->artisan('app:send-premium-expiry-reminders');

    Notification::assertSentToTimes($user, PremiumExpired::class, 1);
});

it('leaves free users and lifetime premium alone', function () {
    $freeUser = User::factory()->create();
    $lifetimeUser = User::factory()->create();
    $lifetimeUser->subscribeTo(SubscriptionPlan::Premium);

    $this->artisan('app:send-premium-expiry-reminders')->assertSuccessful();

    Notification::assertNothingSentTo($freeUser);
    Notification::assertNothingSentTo($lifetimeUser);
});
