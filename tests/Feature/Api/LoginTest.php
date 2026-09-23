<?php

use App\Models\User;

it('trades email and password for a token the phone can keep', function () {
    $user = User::factory()->create(['email' => 'budi@rakku.test', 'password' => bcrypt('rahasia-123')]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'budi@rakku.test',
        'password' => 'rahasia-123',
        'device_name' => 'Redmi Note 13',
    ])->assertSuccessful();

    expect($response->json('token'))->not->toBeEmpty()
        ->and($response->json('user.name'))->toBe($user->name)
        ->and($response->json('books.0.public_id'))->toBe($user->books()->first()->public_id)
        ->and($user->tokens()->where('name', 'Redmi Note 13')->exists())->toBeTrue();
});

it('turns away wrong credentials without hinting which part was wrong', function () {
    User::factory()->create(['email' => 'budi@rakku.test', 'password' => bcrypt('rahasia-123')]);

    $this->postJson('/api/v1/login', [
        'email' => 'budi@rakku.test',
        'password' => 'salah-total',
        'device_name' => 'Redmi Note 13',
    ])->assertStatus(422)->assertJsonValidationErrors(['email' => 'Email atau kata sandi salah.']);
});

it('asks unverified users to verify their email first', function () {
    User::factory()->unverified()->create(['email' => 'budi@rakku.test', 'password' => bcrypt('rahasia-123')]);

    $this->postJson('/api/v1/login', [
        'email' => 'budi@rakku.test',
        'password' => 'rahasia-123',
        'device_name' => 'Redmi Note 13',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('revokes only the token of the device that logs out', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);
    $otherDeviceToken = $user->createToken('Tablet')->plainTextToken;
    $token = $user->createToken('Redmi Note 13')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/logout')->assertSuccessful();

    expect($user->tokens()->pluck('name')->all())->toBe(['Tablet'])
        ->and($otherDeviceToken)->not->toBeEmpty();
});

it('rate limits repeated login attempts', function () {
    User::factory()->create(['email' => 'budi@rakku.test']);

    collect(range(1, 6))->each(fn () => $this->postJson('/api/v1/login', [
        'email' => 'budi@rakku.test',
        'password' => 'salah',
        'device_name' => 'Redmi Note 13',
    ]));

    $this->postJson('/api/v1/login', [
        'email' => 'budi@rakku.test',
        'password' => 'salah',
        'device_name' => 'Redmi Note 13',
    ])->assertStatus(429);
});
