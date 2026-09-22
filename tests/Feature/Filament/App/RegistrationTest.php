<?php

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('registers a new user with a default book and sends a verification email', function () {
    Notification::fake();
    Filament::setCurrentPanel('app');

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Budi',
            'email' => 'budi@rakku.test',
            'password' => 'rahasia-123',
            'passwordConfirmation' => 'rahasia-123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'budi@rakku.test')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->books()->count())->toBe(1);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('sends unverified users to the email verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get("/app/{$user->books()->first()->id}")
        ->assertRedirect('/app/email-verification/prompt');
});
