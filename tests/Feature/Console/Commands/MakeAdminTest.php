<?php

use App\Models\User;

it('grants admin access to a registered user', function () {
    $user = User::factory()->create(['email' => 'pemilik@rakku.test']);

    $this->artisan('app:make-admin', ['email' => 'pemilik@rakku.test'])
        ->expectsOutputToContain('sekarang bisa membuka panel admin')
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

it('verifies the email so the first admin can log in before SMTP works', function () {
    $user = User::factory()->unverified()->create(['email' => 'pemilik@rakku.test']);

    $this->artisan('app:make-admin', ['email' => 'pemilik@rakku.test'])
        ->expectsOutputToContain('ditandai terverifikasi')
        ->assertSuccessful();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('keeps the original verification date for an already verified user', function () {
    $verifiedAt = now()->subMonth();
    $user = User::factory()->create(['email' => 'pemilik@rakku.test', 'email_verified_at' => $verifiedAt]);

    $this->artisan('app:make-admin', ['email' => 'pemilik@rakku.test'])->assertSuccessful();

    expect($user->fresh()->email_verified_at->toDateTimeString())->toBe($verifiedAt->toDateTimeString());
});

it('fails when no user has the given email', function () {
    $this->artisan('app:make-admin', ['email' => 'tidak-ada@rakku.test'])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertFailed();
});
