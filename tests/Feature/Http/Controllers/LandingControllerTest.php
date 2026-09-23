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
