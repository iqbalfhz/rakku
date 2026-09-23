<?php

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('deletes a user on request, together with their books and files', function () {
    $member = User::factory()->create();
    $book = $member->books()->first();
    $account = Account::factory()->for($book)->create();
    Transaction::factory()->for($book)->for($account)->create(['receipt_photo_path' => 'receipts/struk.jpg']);
    Storage::disk('local')->put('receipts/struk.jpg', 'isi foto struk');

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('deleteUser')->table($member), data: ['email' => $member->email])
        ->assertHasNoActionErrors()
        ->assertNotified("Akun {$member->name} sudah dihapus");

    expect(User::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and($book->query()->whereKey($book->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists('receipts/struk.jpg'))->toBeFalse()
        ->and(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
});

it('refuses when the typed email does not match the chosen user', function () {
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('deleteUser')->table($member), data: ['email' => 'salah@rakku.test'])
        ->assertHasActionErrors(['email']);

    expect($member->fresh())->not->toBeNull();
});

it('does not let an admin delete their own account from here', function () {
    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('deleteUser')->table($this->admin));
});
