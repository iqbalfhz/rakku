<?php

use App\Filament\App\Widgets\GettingStarted;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('greets an empty book with the steps that still need doing', function () {
    actingInBook(User::factory()->create());

    expect(GettingStarted::canView())->toBeTrue();

    Livewire::test(GettingStarted::class)
        ->assertSee('Mulai dari sini')
        ->assertSee('Buat akun atau dompet')
        ->assertSee('Catat transaksi pertama')
        ->assertSee('Tinggal 3 langkah lagi');
});

it('counts down as the user works through the steps', function () {
    $book = actingInBook(User::factory()->create());
    $account = Account::factory()->for($book)->create();

    Livewire::test(GettingStarted::class)->assertSee('Tinggal 2 langkah lagi');

    Transaction::factory()->for($book)->for($account)->create();

    Livewire::test(GettingStarted::class)->assertSee('Tinggal 1 langkah lagi');
});

it('disappears once the book is set up', function () {
    $book = actingInBook(User::factory()->create());
    $account = Account::factory()->for($book)->create();
    Transaction::factory()->for($book)->for($account)->create();
    Budget::factory()->for($book)->create();

    expect(GettingStarted::canView())->toBeFalse();
});
