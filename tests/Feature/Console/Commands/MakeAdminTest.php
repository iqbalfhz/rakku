<?php

use App\Models\User;

it('grants admin access to a registered user', function () {
    $user = User::factory()->create(['email' => 'pemilik@rakku.test']);

    $this->artisan('app:make-admin', ['email' => 'pemilik@rakku.test'])
        ->expectsOutputToContain('sekarang bisa membuka panel admin')
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('fails when no user has the given email', function () {
    $this->artisan('app:make-admin', ['email' => 'tidak-ada@rakku.test'])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertFailed();
});
