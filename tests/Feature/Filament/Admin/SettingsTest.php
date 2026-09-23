<?php

use App\Enums\SubscriptionPackage;
use App\Filament\Admin\Pages\Settings;
use App\Filament\App\Pages\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\PublicContact;
use App\Support\SubscriptionConfig;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
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
    Livewire::test(Settings::class)
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
        ->assertNotified('Pengaturan tersimpan');

    expect(SubscriptionConfig::bank())->toBe([
        'name' => 'Bank Mandiri',
        'account_number' => '1370012345678',
        'account_holder' => 'Iqbal Fahrozi',
    ])->and(SubscriptionPackage::Monthly->price())->toBe(30_000)
        ->and(SubscriptionPackage::Yearly->price())->toBe(300_000);
});

it('saves the public contact shown on the landing page', function () {
    Livewire::test(Settings::class)
        ->fillForm([
            'contact' => [
                'whatsapp' => '0812 3456 7890',
                'email' => 'halo@rakku.test',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(PublicContact::whatsAppNumber())->toBe('0812 3456 7890')
        ->and(PublicContact::email())->toBe('halo@rakku.test')
        ->and(PublicContact::whatsAppUrl())->toStartWith('https://wa.me/6281234567890');

    Auth::logout();

    $this->get('/')
        ->assertSee('0812 3456 7890')
        ->assertSee('halo@rakku.test');
});

it('leaves the contact out of the landing page while it is empty', function () {
    Auth::logout();

    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('wa.me', escape: false);
});

it('requires the bank account to stay filled in', function () {
    Livewire::test(Settings::class)
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
        ->get(Settings::getUrl())
        ->assertForbidden();
});
