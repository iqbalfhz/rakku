<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake(Transaction::receiptDisk());

    $this->user = User::factory()->create(['password' => bcrypt('rahasia-sekali')]);

    Sanctum::actingAs($this->user);
});

it('deletes the account and everything in it', function () {
    $book = $this->user->books()->first();
    $account = Account::factory()->for($book)->create();
    $transaction = Transaction::factory()->for($book)->for($account)->create();

    $this->deleteJson('/api/v1/account', [
        'email' => $this->user->email,
        'password' => 'rahasia-sekali',
    ])->assertSuccessful();

    $this->assertModelMissing($this->user);
    $this->assertModelMissing($book);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

it('takes the receipt photos down with it', function () {
    $book = $this->user->books()->first();
    $account = Account::factory()->for($book)->create();
    Storage::disk(Transaction::receiptDisk())->put('receipts/struk.jpg', 'isi foto');
    Transaction::factory()->for($book)->for($account)->create(['receipt_photo_path' => 'receipts/struk.jpg']);

    $this->deleteJson('/api/v1/account', [
        'email' => $this->user->email,
        'password' => 'rahasia-sekali',
    ])->assertSuccessful();

    Storage::disk(Transaction::receiptDisk())->assertMissing('receipts/struk.jpg');
});

it('cuts off every phone that was signed in', function () {
    $this->user->createToken('Ponsel lain');

    $this->deleteJson('/api/v1/account', [
        'email' => $this->user->email,
        'password' => 'rahasia-sekali',
    ])->assertSuccessful();

    expect($this->user->tokens()->count())->toBe(0);
});

it('refuses when the typed email does not match', function () {
    $this->deleteJson('/api/v1/account', [
        'email' => 'orang.lain@contoh.test',
        'password' => 'rahasia-sekali',
    ])->assertJsonValidationErrorFor('email');

    $this->assertModelExists($this->user);
});

it('refuses when the password is wrong', function () {
    $this->deleteJson('/api/v1/account', [
        'email' => $this->user->email,
        'password' => 'tebakan-ngawur',
    ])->assertJsonValidationErrorFor('password');

    $this->assertModelExists($this->user);
});

it('insists on both confirmations', function () {
    $this->deleteJson('/api/v1/account', [])
        ->assertJsonValidationErrors(['email', 'password']);

    $this->assertModelExists($this->user);
});

it('turns away a phone without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->deleteJson('/api/v1/account', ['email' => 'a@b.test', 'password' => 'x'])
        ->assertUnauthorized();
});

it('never lets one account delete another', function () {
    $stranger = User::factory()->create(['password' => bcrypt('rahasia-sekali')]);

    $this->deleteJson('/api/v1/account', [
        'email' => $stranger->email,
        'password' => 'rahasia-sekali',
    ])->assertJsonValidationErrorFor('email');

    $this->assertModelExists($stranger);
    $this->assertModelExists($this->user);
});
