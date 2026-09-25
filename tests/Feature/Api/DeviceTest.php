<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Token sungguhan lewat header, bukan Sanctum::actingAs: yang diuji di sini
    // justru identitas tokennya, dan penyamaran tidak punya baris di database.
    $this->current = $this->user->createToken('Ponsel sekarang');
});

it('lists every phone that can still open this account', function () {
    $other = $this->user->createToken('Ponsel lama')->accessToken;

    $response = $this->withToken($this->current->plainTextToken)
        ->getJson('/api/v1/devices')
        ->assertSuccessful();

    $devices = collect($response->json('devices'));

    expect($devices)->toHaveCount(2)
        ->and($devices->firstWhere('name', 'Ponsel lama')['id'])->toBe($other->id)
        ->and($devices->firstWhere('name', 'Ponsel sekarang')['is_current'])->toBeTrue()
        ->and($devices->firstWhere('name', 'Ponsel lama')['is_current'])->toBeFalse();
});

it('cuts off a phone that was lost', function () {
    $lost = $this->user->createToken('Ponsel hilang')->accessToken;

    $this->withToken($this->current->plainTextToken)
        ->deleteJson("/api/v1/devices/{$lost->id}")
        ->assertSuccessful();

    expect($this->user->tokens()->whereKey($lost->id)->exists())->toBeFalse()
        ->and($this->user->tokens()->count())->toBe(1);
});

it('sends the user to the sign-out button for the phone in their hand', function () {
    $this->withToken($this->current->plainTextToken)
        ->deleteJson("/api/v1/devices/{$this->current->accessToken->id}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Gunakan tombol Keluar untuk perangkat ini.']);

    expect($this->user->tokens()->count())->toBe(1);
});

it('never lets one account cut off another', function () {
    $stranger = User::factory()->create();
    $strangerToken = $stranger->createToken('Ponsel orang lain')->accessToken;

    $this->withToken($this->current->plainTextToken)
        ->deleteJson("/api/v1/devices/{$strangerToken->id}")
        ->assertNotFound();

    expect($stranger->tokens()->count())->toBe(1);
});

it('never shows one account the phones of another', function () {
    User::factory()->create()->createToken('Ponsel orang lain');

    $response = $this->withToken($this->current->plainTextToken)
        ->getJson('/api/v1/devices')
        ->assertSuccessful();

    expect(collect($response->json('devices'))->pluck('name'))->not->toContain('Ponsel orang lain');
});

it('turns away a phone without a token', function () {
    $this->getJson('/api/v1/devices')->assertUnauthorized();
});
