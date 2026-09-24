<?php

use App\Services\PlanGate;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Jawaban halaman langganan dari server.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeSubscriptionPage(array $overrides = []): void
{
    Http::fake([
        '*/api/v1/subscription' => Http::response([
            'plan' => ['is_premium' => false, 'expires_at' => null],
            'bank' => ['name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Iqbal Fahrozi'],
            'packages' => [
                ['value' => 'monthly', 'months' => 1, 'price' => 25_000],
                ['value' => 'quarterly', 'months' => 3, 'price' => 65_000],
                ['value' => 'yearly', 'months' => 12, 'price' => 250_000],
            ],
            'pending_payment' => null,
            'payments' => [],
            ...$overrides,
        ]),
        '*/api/v1/subscription/payments' => Http::response(['payment' => ['status' => 'pending']], 201),
    ]);
}

/**
 * Foto bukti transfer yang sudah dijepret.
 */
function takenProof(): string
{
    $cameraPath = Storage::disk('local')->path('kamera-sementara.jpg');
    Storage::disk('local')->put('kamera-sementara.jpg', 'hasil jepretan');

    return $cameraPath;
}

it('shows where to transfer and what each package costs', function () {
    fakeSubscriptionPage();

    Livewire::test('subscription')
        ->assertSee('1234567890')
        ->assertSee('BCA')
        ->assertSee('Iqbal Fahrozi')
        ->assertSee('Rp 25.000')
        ->assertSee('Rp 250.000');
});

it('sends the transfer proof and frees the space it took', function () {
    fakeSubscriptionPage();

    $component = Livewire::test('subscription')
        ->set('package', 'quarterly')
        ->call('photoTaken', takenProof());

    $storedPath = $component->get('proofPath');

    $component->set('note', 'Atas nama istri saya')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('proofPath', null)
        ->assertSee('Bukti transfer terkirim');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/subscription/payments'));

    expect(Storage::disk('local')->exists($storedPath))->toBeFalse();
});

it('will not send without a picture of the transfer', function () {
    fakeSubscriptionPage();

    Livewire::test('subscription')
        ->call('submit')
        ->assertHasErrors('proofPath')
        ->assertSee('Fotokan dulu bukti transfernya');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/subscription/payments'));
});

it('passes on the reason when the server refuses', function () {
    Http::fake([
        '*/api/v1/subscription' => Http::response([
            'plan' => ['is_premium' => false, 'expires_at' => null],
            'bank' => ['name' => 'BCA', 'account_number' => '123', 'account_holder' => 'Iqbal'],
            'packages' => [['value' => 'monthly', 'months' => 1, 'price' => 25_000]],
            'pending_payment' => null,
            'payments' => [],
        ]),
        '*/api/v1/subscription/payments' => Http::response(['message' => 'Masih ada pengajuan yang menunggu diverifikasi admin.'], 409),
    ]);

    Livewire::test('subscription')
        ->call('photoTaken', takenProof())
        ->call('submit')
        ->assertSet('error', 'Masih ada pengajuan yang menunggu diverifikasi admin.');
});

it('shows the waiting submission instead of the form', function () {
    fakeSubscriptionPage([
        'pending_payment' => [
            'package' => 'monthly',
            'months' => 1,
            'amount' => 25_000,
            'status' => 'pending',
            'status_label' => 'Menunggu',
            'note' => null,
            'submitted_at' => '2026-09-24T03:00:00Z',
            'reviewed_at' => null,
        ],
    ]);

    Livewire::test('subscription')
        ->assertSee('Sedang diperiksa')
        ->assertDontSee('Fotokan bukti transfer');
});

it('says premium is on and until when', function () {
    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => '2026-12-31T16:59:59Z']);
    fakeSubscriptionPage(['plan' => ['is_premium' => true, 'expires_at' => '2026-12-31T16:59:59Z']]);

    Livewire::test('subscription')
        ->assertSee('Premium aktif')
        ->assertSee('Berlaku sampai');
});

it('admits it needs a signal instead of showing an empty page', function () {
    Http::fake(['*/api/v1/subscription' => Http::response('', 500)]);

    Livewire::test('subscription')
        ->assertSet('details', null)
        ->assertSee('Halaman langganan butuh sinyal');
});

it('leads a locked screen to the subscription page, not to a browser', function () {
    Livewire::test('debts')
        ->assertSee('Aktifkan premium')
        ->assertDontSee('rakku.iqbalfhz.my.id');
});
