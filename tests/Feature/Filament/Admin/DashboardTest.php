<?php

use App\Enums\SubscriptionPlan;
use App\Filament\Admin\Widgets\ExpiringPremiumTable;
use App\Filament\Admin\Widgets\NewUsersChart;
use App\Filament\Admin\Widgets\PendingPaymentsTable;
use App\Filament\Admin\Widgets\UserStatsOverview;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('summarizes total, premium, new, and soon-expiring users', function () {
    $this->travelTo('2026-09-15 10:00:00');
    User::factory()->count(2)->create(['created_at' => '2026-08-10']);
    $this->actingAs(User::factory()->admin()->create());
    User::factory()->premium()->create();
    User::factory()->create()->subscribeTo(SubscriptionPlan::Premium, now()->addDays(3));

    Livewire::test(UserStatsOverview::class)
        ->assertSeeInOrder(['Total pengguna', '5'])
        ->assertSeeInOrder(['Pengguna premium', '2', '40% dari total pengguna'])
        ->assertSeeInOrder(['Pengguna baru bulan ini', '3', '+50% dari bulan lalu (2)'])
        ->assertSeeInOrder(['Premium segera berakhir', '1']);
});

it('lists only premium users ending within the window, closest first', function () {
    $this->freezeTime();
    $this->actingAs(User::factory()->admin()->create());
    $endingInTen = User::factory()->create();
    $endingInTen->subscribeTo(SubscriptionPlan::Premium, now()->addDays(10));
    $endingInTwo = User::factory()->create();
    $endingInTwo->subscribeTo(SubscriptionPlan::Premium, now()->addDays(2));
    $endingLater = User::factory()->create();
    $endingLater->subscribeTo(SubscriptionPlan::Premium, now()->addDays(ExpiringPremiumTable::WINDOW_DAYS + 5));
    $lifetime = User::factory()->premium()->create();

    Livewire::test(ExpiringPremiumTable::class)
        ->assertCanSeeTableRecords([$endingInTwo, $endingInTen], inOrder: true)
        ->assertCanNotSeeTableRecords([$endingLater, $lifetime])
        ->assertSee('Sisa 2 hari');
});

it('extends an expiring premium from the dashboard', function () {
    $this->freezeTime();
    $this->actingAs(User::factory()->admin()->create());
    $member = User::factory()->create();
    $member->subscribeTo(SubscriptionPlan::Premium, now()->addDays(2));

    Livewire::test(ExpiringPremiumTable::class)
        ->callAction(TestAction::make('activatePremium')->table($member), data: ['duration' => 'yearly'])
        ->assertHasNoFormErrors();

    expect($member->fresh()->currentSubscription->expires_at->toDateString())
        ->toBe(now()->addDays(2)->addYear()->toDateString());
});

it('labels the dashboard as Dashboard instead of Dasbor', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSee('Dashboard')
        ->assertDontSee('Dasbor');
});

it('renders the monthly sign-up chart', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(NewUsersChart::class)->assertOk();
});

it('puts pending payments on the dashboard and lets the admin approve them there', function () {
    $this->actingAs(User::factory()->admin()->create());
    $pendingPayment = SubscriptionPayment::factory()->create();
    $approvedPayment = SubscriptionPayment::factory()->approved()->create();

    Livewire::test(PendingPaymentsTable::class)
        ->assertCanSeeTableRecords([$pendingPayment])
        ->assertCanNotSeeTableRecords([$approvedPayment])
        ->callAction(TestAction::make('approvePayment')->table($pendingPayment));

    expect($pendingPayment->user->fresh()->isPremium())->toBeTrue();
});
