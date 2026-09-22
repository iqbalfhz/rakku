<?php

use App\Filament\App\Pages\Tenancy\RegisterBook;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('hides the new book page from free users who already own a book', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app/new')->assertNotFound();
});

it('lets premium users create an additional book with default categories', function () {
    $user = User::factory()->premium()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel('app');

    Livewire::test(RegisterBook::class)
        ->fillForm(['name' => "Nadi's Fotocopy"])
        ->call('register')
        ->assertHasNoFormErrors();

    $book = $user->books()->where('name', "Nadi's Fotocopy")->sole();

    expect($book->is_default)->toBeFalse()
        ->and($book->categories()->count())->toBe(12);
});
