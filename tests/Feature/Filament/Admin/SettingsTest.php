<?php

use App\Enums\SubscriptionPackage;
use App\Filament\Admin\Pages\SubscriptionSettings;
use App\Filament\App\Pages\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\SubscriptionConfig;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
});

it('falls back to the config file while nothing has been saved', function () {
    expect(SubscriptionConfig::bank())->toBe(config('subscription.bank'))
        ->and(SubscriptionConfig::prices())->toBe(config('subscription.prices'));
});

it('saves the bank account and package prices from the admin panel', function () {
    Livewire::test(SubscriptionSettings::class)
        ->fillForm([
            'bank' => [
                'name' => 'Bank Mandiri',
                'account_number' => '1370012345678',
                'account_holder' => 'Iqbal Fahrozi',
            ],
            'prices' => [
                'monthly' => 30_000,
                'quarterly' => 80_000,
                'yearly' => 300_000,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Pengaturan langganan tersimpan');

    expect(SubscriptionConfig::bank())->toBe([
        'name' => 'Bank Mandiri',
        'account_number' => '1370012345678',
        'account_holder' => 'Iqbal Fahrozi',
    ])->and(SubscriptionPackage::Monthly->price())->toBe(30_000)
        ->and(SubscriptionPackage::Yearly->price())->toBe(300_000);
});

it('requires the bank account to stay filled in', function () {
    Livewire::test(SubscriptionSettings::class)
        ->fillForm(['bank' => ['name' => '', 'account_number' => '', 'account_holder' => '']])
        ->call('save')
        ->assertHasFormErrors([
            'bank.name' => 'required',
            'bank.account_number' => 'required',
            'bank.account_holder' => 'required',
        ]);
});

it('shows the saved bank account to users on the subscription page', function () {
    SubscriptionConfig::save(
        ['name' => 'Bank Mandiri', 'account_number' => '1370012345678', 'account_holder' => 'Iqbal Fahrozi'],
        config('subscription.prices'),
    );
    $user = User::factory()->create();
    actingInBook($user);

    Livewire::test(Subscription::class)
        ->assertSee('Bank Mandiri')
        ->assertSee('1370012345678');
});

it('keeps the price that applied when a payment was submitted', function () {
    $payment = SubscriptionPayment::factory()->package(SubscriptionPackage::Monthly)->create();
    $priceWhenSubmitted = (float) $payment->amount;

    SubscriptionConfig::save(config('subscription.bank'), ['monthly' => 50_000, 'quarterly' => 130_000, 'yearly' => 500_000]);

    expect((float) $payment->fresh()->amount)->toBe($priceWhenSubmitted)
        ->and(SubscriptionPackage::Monthly->price())->toBe(50_000);
});

it('keeps non-admin users out of the settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get(SubscriptionSettings::getUrl())
        ->assertForbidden();
});
