<?php

use App\Enums\SubscriptionPlan;
use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('opens an account from the phone, ready to use', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertCreated()->assertJson(['email' => 'budi@contoh.test']);

    $user = User::query()->where('email', 'budi@contoh.test')->sole();

    expect($user->name)->toBe('Budi')
        ->and($user->currentSubscription->plan)->toBe(SubscriptionPlan::Free)
        ->and($user->books()->count())->toBe(1)
        ->and($user->books()->first()->name)->toBe(Book::DEFAULT_NAME);
});

it('sends the verification email instead of handing over a token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertCreated();

    // Token sebelum verifikasi berarti verifikasinya sia-sia.
    expect($response->json())->not->toHaveKey('token');

    Notification::assertSentTo(User::query()->sole(), VerifyEmail::class);
});

it('keeps an unverified newcomer out until they open the email', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertCreated();

    $this->postJson('/api/v1/login', [
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
        'device_name' => 'Ponsel RakKu',
    ])->assertJsonValidationErrorFor('email');
});

it('lets them in once the email is verified', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertCreated();

    User::query()->sole()->markEmailAsVerified();

    $this->postJson('/api/v1/login', [
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
        'device_name' => 'Ponsel RakKu',
    ])->assertSuccessful()->assertJsonStructure(['token', 'books']);
});

it('says plainly when the email is already taken', function () {
    User::factory()->create(['email' => 'budi@contoh.test']);

    $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertJsonValidationErrorFor('email')
        ->assertJsonFragment(['email' => ['Email ini sudah terdaftar. Masuk saja dengan email itu.']]);
});

it('refuses a password too short to be worth anything', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'password' => 'pendek',
    ])->assertJsonValidationErrorFor('password');

    expect(User::query()->count())->toBe(0);
});

it('insists on all three fields', function () {
    $this->postJson('/api/v1/register', [])
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('stops someone opening accounts in bulk', function () {
    foreach (range(1, 3) as $number) {
        $this->postJson('/api/v1/register', [
            'name' => "Budi {$number}",
            'email' => "budi{$number}@contoh.test",
            'password' => 'rahasia-sekali',
        ])->assertCreated();
    }

    $this->postJson('/api/v1/register', [
        'name' => 'Budi 4',
        'email' => 'budi4@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertStatus(429);
});
