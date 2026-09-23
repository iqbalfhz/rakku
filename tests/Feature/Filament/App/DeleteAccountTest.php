<?php

use App\Filament\App\Pages\Auth\EditProfile;
use App\Models\Account;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

it('deletes the account with everything in it, including uploaded files', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);
    $book = $user->books()->first();
    $account = Account::factory()->for($book)->create();
    $transaction = Transaction::factory()->for($book)->for($account)->create([
        'receipt_photo_path' => 'receipts/struk.jpg',
    ]);
    SupportTicket::factory()->for($user)->create();
    Storage::disk('local')->put('receipts/struk.jpg', 'isi foto struk');

    actingInBook($user);

    Livewire::test(EditProfile::class)
        ->callAction(TestAction::make('deleteAccount')->schemaComponent('delete-account', schema: 'content'), data: [
            'email' => $user->email,
            'password' => 'rahasia-123',
        ])
        ->assertHasNoActionErrors();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and($book->query()->whereKey($book->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        ->and(SupportTicket::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('receipts/struk.jpg'))->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});

it('refuses when the typed email does not match the account', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);
    actingInBook($user);

    Livewire::test(EditProfile::class)
        ->callAction(TestAction::make('deleteAccount')->schemaComponent('delete-account', schema: 'content'), data: [
            'email' => 'bukan-email-saya@rakku.test',
            'password' => 'rahasia-123',
        ])
        ->assertHasActionErrors(['email']);

    expect($user->fresh())->not->toBeNull();
});

it('refuses when the password is wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);
    actingInBook($user);

    Livewire::test(EditProfile::class)
        ->callAction(TestAction::make('deleteAccount')->schemaComponent('delete-account', schema: 'content'), data: [
            'email' => $user->email,
            'password' => 'salah-total',
        ])
        ->assertHasActionErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

it('leaves other people accounts and files untouched', function () {
    $otherUser = User::factory()->create();
    $otherBook = $otherUser->books()->first();
    $otherAccount = Account::factory()->for($otherBook)->create();
    Transaction::factory()->for($otherBook)->for($otherAccount)->create([
        'receipt_photo_path' => 'receipts/struk-orang-lain.jpg',
    ]);
    Storage::disk('local')->put('receipts/struk-orang-lain.jpg', 'punya orang lain');

    $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);
    actingInBook($user);

    Livewire::test(EditProfile::class)
        ->callAction(TestAction::make('deleteAccount')->schemaComponent('delete-account', schema: 'content'), data: [
            'email' => $user->email,
            'password' => 'rahasia-123',
        ])
        ->assertHasNoActionErrors();

    expect($otherUser->fresh())->not->toBeNull()
        ->and($otherBook->fresh())->not->toBeNull()
        ->and(Storage::disk('local')->exists('receipts/struk-orang-lain.jpg'))->toBeTrue();
});
