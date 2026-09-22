<?php

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPaymentStatus;
use App\Filament\App\Pages\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets a free user submit a transfer proof and notifies the admins', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    actingInBook($user);

    Livewire::test(Subscription::class)
        ->fillForm([
            'package' => SubscriptionPackage::Quarterly->value,
            'proof_path' => UploadedFile::fake()->image('bukti-transfer.jpg'),
            'note' => 'Transfer atas nama Budi Santoso.',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified('Bukti transfer terkirim');

    $payment = $user->subscriptionPayments()->sole();

    expect($payment->package)->toBe(SubscriptionPackage::Quarterly)
        ->and((float) $payment->amount)->toBe((float) SubscriptionPackage::Quarterly->price())
        ->and($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->note)->toBe('Transfer atas nama Budi Santoso.')
        ->and(Storage::disk('local')->exists($payment->proof_path))->toBeTrue()
        ->and($admin->notifications()->count())->toBe(1)
        ->and($user->fresh()->isPremium())->toBeFalse();
});

it('requires both a package and a proof of transfer', function () {
    actingInBook(User::factory()->create());

    Livewire::test(Subscription::class)
        ->fillForm(['package' => null, 'proof_path' => null])
        ->call('submit')
        ->assertHasFormErrors(['package' => 'required', 'proof_path' => 'required']);
});

it('hides the form while a submission is still waiting for verification', function () {
    $user = User::factory()->create();
    SubscriptionPayment::factory()->for($user)->create();
    actingInBook($user);

    Livewire::test(Subscription::class)
        ->assertSee('Menunggu verifikasi')
        ->assertDontSee('Kirim bukti transfer');
});

it('shows the rejection reason in the submission history', function () {
    $user = User::factory()->create();
    SubscriptionPayment::factory()->for($user)->rejected('Nominal kurang Rp 5.000.')->create();
    actingInBook($user);

    Livewire::test(Subscription::class)
        ->assertSee('Nominal kurang Rp 5.000.')
        ->assertSee('Kirim bukti transfer');
});

it('is reachable by free and premium users alike', function (bool $isPremium) {
    $user = $isPremium ? User::factory()->premium()->create() : User::factory()->create();
    $book = $user->books()->first();

    $this->actingAs($user)->get("/app/{$book->public_id}/langganan")->assertSuccessful();
})->with([
    'free' => false,
    'premium' => true,
]);
