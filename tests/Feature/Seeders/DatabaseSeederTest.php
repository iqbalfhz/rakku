<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the demo admin and ledger outside production', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->sole();

    expect($admin->is_admin)->toBeTrue()
        ->and($admin->isPremium())->toBeTrue()
        ->and($admin->books()->sole()->accounts()->pluck('name')->all())->toBe(['Cash', 'BCA', 'GoPay']);
});

it('refuses to create the demo accounts in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])
        ->expectsOutputToContain('Seeder demo tidak dijalankan di production')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
