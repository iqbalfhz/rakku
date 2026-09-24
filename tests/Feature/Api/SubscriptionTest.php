<?php

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentSubmitted;
use App\Support\SubscriptionConfig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(SubscriptionPayment::proofDisk());
    Notification::fake();

    $this->user = User::factory()->create();

    Sanctum::actingAs($this->user);
});

it('tells the phone where to transfer and how much', function () {
    SubscriptionConfig::save(
        ['name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Iqbal Fahrozi'],
        ['monthly' => 25_000, 'quarterly' => 65_000, 'yearly' => 250_000],
    );

    $response = $this->getJson('/api/v1/subscription')->assertSuccessful();

    expect($response->json('bank.name'))->toBe('BCA')
        ->and($response->json('bank.account_number'))->toBe('1234567890')
        ->and($response->json('packages'))->toHaveCount(3)
        ->and(collect($response->json('packages'))->firstWhere('value', 'yearly'))
        ->toMatchArray(['months' => 12, 'price' => 250_000])
        ->and($response->json('plan.is_premium'))->toBeFalse();
});

it('reports an active subscription and when it runs out', function () {
    $this->user->subscribeTo(SubscriptionPlan::Premium, now()->addMonth());

    $response = $this->getJson('/api/v1/subscription')->assertSuccessful();

    expect($response->json('plan.is_premium'))->toBeTrue()
        ->and($response->json('plan.expires_at'))->toEndWith('Z');
});

it('takes a transfer proof sent from the phone and tells the admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->postJson('/api/v1/subscription/payments', [
        'package' => 'quarterly',
        'proof' => UploadedFile::fake()->image('struk.jpg'),
        'note' => 'Transfer atas nama istri saya',
    ])->assertCreated();

    $payment = SubscriptionPayment::query()->sole();

    expect($payment->user_id)->toBe($this->user->id)
        ->and($payment->package)->toBe(SubscriptionPackage::Quarterly)
        ->and((float) $payment->amount)->toBe((float) SubscriptionPackage::Quarterly->price())
        ->and($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->note)->toBe('Transfer atas nama istri saya')
        ->and($response->json('payment.status'))->toBe('pending');

    Storage::disk(SubscriptionPayment::proofDisk())->assertExists($payment->proof_path);
    Notification::assertSentTo($admin, SubscriptionPaymentSubmitted::class);
});

it('refuses a second submission while one is still waiting', function () {
    SubscriptionPayment::factory()->for($this->user)->create(['status' => SubscriptionPaymentStatus::Pending]);

    $this->postJson('/api/v1/subscription/payments', [
        'package' => 'monthly',
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertStatus(409);

    expect(SubscriptionPayment::query()->count())->toBe(1);
});

it('lets a new submission through once the last one was reviewed', function () {
    SubscriptionPayment::factory()->for($this->user)->create(['status' => SubscriptionPaymentStatus::Rejected]);

    $this->postJson('/api/v1/subscription/payments', [
        'package' => 'monthly',
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertCreated();

    expect(SubscriptionPayment::query()->count())->toBe(2);
});

it('insists on a picture of the transfer', function () {
    $this->postJson('/api/v1/subscription/payments', ['package' => 'monthly'])
        ->assertJsonValidationErrorFor('proof');

    $this->postJson('/api/v1/subscription/payments', [
        'package' => 'monthly',
        'proof' => UploadedFile::fake()->create('struk.pdf', 100, 'application/pdf'),
    ])->assertJsonValidationErrorFor('proof');
});

it('turns down a package nobody sells', function () {
    $this->postJson('/api/v1/subscription/payments', [
        'package' => 'decade',
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ])->assertJsonValidationErrorFor('package');
});

it('hands back the history so the phone can show what happened', function () {
    SubscriptionPayment::factory()->for($this->user)->create([
        'package' => SubscriptionPackage::Monthly,
        'status' => SubscriptionPaymentStatus::Approved,
    ]);

    $response = $this->getJson('/api/v1/subscription')->assertSuccessful();

    expect($response->json('payments'))->toHaveCount(1)
        ->and($response->json('payments.0.status'))->toBe('approved')
        ->and($response->json('payments.0.status_label'))->not->toBeEmpty()
        ->and($response->json('pending_payment'))->toBeNull();
});

it('never shows one account the payments of another', function () {
    SubscriptionPayment::factory()->for(User::factory())->create();

    $response = $this->getJson('/api/v1/subscription')->assertSuccessful();

    expect($response->json('payments'))->toBeEmpty();
});

it('turns away a phone without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/subscription')->assertUnauthorized();
});
