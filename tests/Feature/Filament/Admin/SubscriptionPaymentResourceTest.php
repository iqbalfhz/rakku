<?php

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Filament\Admin\Resources\SubscriptionPayments\Pages\ListSubscriptionPayments;
use App\Filament\Admin\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentApproved;
use App\Notifications\SubscriptionPaymentRejected;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('activates premium for the paid period when a payment is approved', function () {
    $this->travelTo('2026-10-01');
    $member = User::factory()->create();
    $payment = SubscriptionPayment::factory()->for($member)->package(SubscriptionPackage::Quarterly)->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->callAction(TestAction::make('approvePayment')->table($payment));

    $member = $member->fresh();

    expect($member->isPremium())->toBeTrue()
        ->and($member->currentSubscription->expires_at->toDateString())->toBe('2027-01-01')
        ->and($payment->fresh())
        ->status->toBe(SubscriptionPaymentStatus::Approved)
        ->reviewed_by->toBe($this->admin->id)
        ->and($member->notifications()->count())->toBe(1);
});

it('adds the new period on top of a premium that is still running', function () {
    $this->travelTo('2026-10-01');
    $member = User::factory()->create();
    $member->subscribeTo(SubscriptionPlan::Premium, now()->addDays(20));
    $payment = SubscriptionPayment::factory()->for($member)->package(SubscriptionPackage::Monthly)->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->callAction(TestAction::make('approvePayment')->table($payment));

    expect($member->fresh()->currentSubscription->expires_at->toDateString())->toBe('2026-11-21');
});

it('rejects a payment with a reason the user can read', function () {
    $member = User::factory()->create();
    $payment = SubscriptionPayment::factory()->for($member)->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->callAction(TestAction::make('rejectPayment')->table($payment), data: [
            'rejection_reason' => 'Nominal transfer kurang Rp 5.000.',
        ])
        ->assertHasNoFormErrors();

    expect($payment->fresh())
        ->status->toBe(SubscriptionPaymentStatus::Rejected)
        ->rejection_reason->toBe('Nominal transfer kurang Rp 5.000.')
        ->and($member->fresh()->isPremium())->toBeFalse()
        ->and($member->notifications()->sole()->data['body'])->toBe('Nominal transfer kurang Rp 5.000.');
});

it('emails the user as well as ringing the bell after a review', function (string $action, string $notificationClass, array $data) {
    NotificationFacade::fake();
    $member = User::factory()->create();
    $payment = SubscriptionPayment::factory()->for($member)->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->callAction(TestAction::make($action)->table($payment), data: $data)
        ->assertHasNoFormErrors();

    NotificationFacade::assertSentTo(
        $member,
        $notificationClass,
        fn (object $notification, array $channels): bool => $channels === ['database', 'mail'],
    );
})->with([
    'disetujui' => ['approvePayment', SubscriptionPaymentApproved::class, []],
    'ditolak' => ['rejectPayment', SubscriptionPaymentRejected::class, ['rejection_reason' => 'Nominal tidak sesuai.']],
]);

it('needs a reason before a payment can be rejected', function () {
    $payment = SubscriptionPayment::factory()->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->callAction(TestAction::make('rejectPayment')->table($payment), data: ['rejection_reason' => ''])
        ->assertHasActionErrors(['rejection_reason' => 'required']);

    expect($payment->fresh()->isPending())->toBeTrue();
});

it('only offers the review actions while a payment is pending', function () {
    $approvedPayment = SubscriptionPayment::factory()->approved()->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->filterTable('status', SubscriptionPaymentStatus::Approved->value)
        ->assertActionHidden(TestAction::make('approvePayment')->table($approvedPayment))
        ->assertActionHidden(TestAction::make('rejectPayment')->table($approvedPayment));
});

it('counts the pending payments in the admin menu badge', function () {
    SubscriptionPayment::factory()->count(2)->create();
    SubscriptionPayment::factory()->approved()->create();

    expect(SubscriptionPaymentResource::getNavigationBadge())->toBe('2');
});

it('shows only pending payments until the filter is changed', function () {
    $pendingPayment = SubscriptionPayment::factory()->create();
    $approvedPayment = SubscriptionPayment::factory()->approved()->create();

    Livewire::test(ListSubscriptionPayments::class)
        ->assertCanSeeTableRecords([$pendingPayment])
        ->assertCanNotSeeTableRecords([$approvedPayment]);
});

it('keeps non-admin users out of the payment list', function () {
    $this->actingAs(User::factory()->create())
        ->get(SubscriptionPaymentResource::getUrl('index'))
        ->assertForbidden();
});
