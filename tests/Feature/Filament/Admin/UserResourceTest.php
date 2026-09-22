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

it('activates premium with a one-click duration button', function (string $duration, string $expectedExpiry) {
    $this->travelTo('2026-10-01');
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: ['duration' => $duration])
        ->assertHasNoFormErrors();

    $member = $member->fresh();

    expect($member->isPremium())->toBeTrue()
        ->and($member->currentSubscription->expires_at->toDateString())->toBe($expectedExpiry);
})->with([
    '1 bulan' => ['monthly', '2026-11-01'],
    '3 bulan' => ['quarterly', '2027-01-01'],
    '12 bulan' => ['yearly', '2027-10-01'],
]);

it('adds the chosen duration on top of a premium that is still running', function () {
    $this->travelTo('2026-10-01');
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();
    $member->subscribeTo(SubscriptionPlan::Premium, now()->addDays(10));

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: ['duration' => 'monthly']);

    expect($member->fresh()->currentSubscription->expires_at->toDateString())->toBe('2026-11-11');
});

it('still allows a custom expiry date and unlimited premium', function (array $data, ?string $expectedExpiry) {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: $data)
        ->assertHasNoFormErrors();

    $member = $member->fresh();

    expect($member->isPremium())->toBeTrue()
        ->and($member->currentSubscription->expires_at?->toDateString())->toBe($expectedExpiry);
})->with([
    'tanggal khusus' => [['duration' => 'custom', 'expires_at' => '2026-12-24'], '2026-12-24'],
    'tanpa batas' => [['duration' => 'unlimited'], null],
]);

it('needs a date when the custom duration is chosen', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: [
            'duration' => 'custom',
            'expires_at' => null,
        ])
        ->assertHasActionErrors(['expires_at' => 'required']);

    expect($member->fresh()->isPremium())->toBeFalse();
});

it('grants and revokes admin access for another user', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleAdmin')->table($member));

    expect($member->fresh()->is_admin)->toBeTrue();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleAdmin')->table($member->fresh()));

    expect($member->fresh()->is_admin)->toBeFalse();
});

it('does not let admins revoke their own admin access', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('toggleAdmin')->table($admin));
});

it('verifies a user email manually so they can open their book', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('verifyEmailManually')->table($member))
        ->assertNotified("Email {$member->email} terverifikasi");

    expect($member->fresh()->hasVerifiedEmail())->toBeTrue();

    Filament::setCurrentPanel('app');
    $this->actingAs($member->fresh())
        ->get("/app/{$member->books()->first()->public_id}")
        ->assertSuccessful();
});

it('only offers manual verification for unverified users', function () {
    $this->actingAs(User::factory()->admin()->create());
    $verifiedMember = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('verifyEmailManually')->table($verifiedMember));
});

it('filters users whose email is not verified yet', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $unverifiedMember = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$unverifiedMember])
        ->assertCanNotSeeTableRecords([$admin]);
});

it('filters users by plan', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $premiumMember = User::factory()->premium()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('premium', true)
        ->assertCanSeeTableRecords([$premiumMember])
        ->assertCanNotSeeTableRecords([$admin])
        ->filterTable('premium', false)
        ->assertCanSeeTableRecords([$admin])
        ->assertCanNotSeeTableRecords([$premiumMember]);
});

it('downgrades a premium user back to the free plan', function () {
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->premium()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('downgradeToFree')->table($member));

    expect($member->fresh()->currentSubscription->plan)->toBe(SubscriptionPlan::Free)
        ->and($member->fresh()->isPremium())->toBeFalse();
});
