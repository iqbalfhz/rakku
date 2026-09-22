<?php

use App\Enums\SubscriptionPlan;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('activates premium manually with an expiry date', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: [
            'expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertHasNoFormErrors();

    $subscription = $member->fresh()->currentSubscription;

    expect($member->fresh()->isPremium())->toBeTrue()
        ->and($subscription->expires_at->toDateString())->toBe(now()->addMonth()->toDateString());
});

it('downgrades a premium user back to the free plan', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->premium()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('downgradeToFree')->table($member));

    expect($member->fresh()->currentSubscription->plan)->toBe(SubscriptionPlan::Free)
        ->and($member->fresh()->isPremium())->toBeFalse();
});
