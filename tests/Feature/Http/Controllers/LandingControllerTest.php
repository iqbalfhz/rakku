<?php

use App\Models\User;
use App\Support\SubscriptionConfig;

it('welcomes guests with the pitch, the plans, and a way in', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Pembukuan yang')
        ->assertSee('Invoice ke klien + PDF siap kirim')
        ->assertSee('/app/login', escape: false)
        ->assertSee('/app/register', escape: false);
});

it('lists every paid and free feature the app actually has', function (string $feature) {
    $this->get('/')->assertSee($feature);
})->with([
    'insight dasar' => 'Insight pengeluaran terbesar',
    'insight lanjutan' => 'Insight tren & perbandingan antar bulan',
    'export excel' => 'Export Excel format lengkap',
    'buku tambahan' => 'Buku kedua dan seterusnya',
]);

it('answers what happens to the data once premium ends', function () {
    $this->get('/')
        ->assertSee('Kalau premium berakhir, data saya hilang?')
        ->assertSee('Apakah langganannya otomatis memperpanjang?');
});

it('links to the privacy policy and the terms', function () {
    $this->get('/')
        ->assertSee('Kebijakan privasi')
        ->assertSee('kebijakan-privasi', escape: false)
        ->assertSee('syarat-layanan', escape: false);
});

it('quotes the prices that the admin saved', function () {
    SubscriptionConfig::save(config('subscription.bank'), [
        'monthly' => 33_000,
        'quarterly' => 90_000,
        'yearly' => 333_000,
    ]);

    $this->get('/')
        ->assertSee('33.000')
        ->assertSee('333.000');
});

it('sends a signed-in user straight to their book', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect("/app/{$user->books()->first()->public_id}");
});
