<?php

use App\Models\Book;
use App\Models\Debt;
use App\Models\User;

it('reminds premium users about debts due in 3 days, tomorrow, and today', function (int $daysUntilDue, string $expectedTitle) {
    $this->travelTo('2026-05-01');
    $user = User::factory()->premium()->create();
    Debt::factory()->receivable()->for(Book::factory()->for($user))->create([
        'counterparty_name' => 'Budi',
        'due_date' => today()->addDays($daysUntilDue),
    ]);

    $this->artisan('app:send-debt-reminders')->assertSuccessful();

    expect($user->notifications)->toHaveCount(1)
        ->and($user->notifications->first()->data['title'])->toBe($expectedTitle);
})->with([
    '3 days before' => [3, 'Piutang dari Budi jatuh tempo 3 hari lagi'],
    '1 day before' => [1, 'Piutang dari Budi jatuh tempo besok'],
    'due today' => [0, 'Piutang dari Budi jatuh tempo hari ini'],
]);

it('does not remind on days outside the reminder schedule', function () {
    $this->travelTo('2026-05-01');
    $user = User::factory()->premium()->create();
    Debt::factory()->for(Book::factory()->for($user))->create(['due_date' => today()->addDays(2)]);

    $this->artisan('app:send-debt-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(0);
});

it('does not remind when the reminder is disabled or the user is on the free plan', function (bool $isPremium, bool $reminderEnabled) {
    $this->travelTo('2026-05-01');
    $user = $isPremium ? User::factory()->premium()->create() : User::factory()->create();
    Debt::factory()->for(Book::factory()->for($user))->create([
        'due_date' => today(),
        'reminder_enabled' => $reminderEnabled,
    ]);

    $this->artisan('app:send-debt-reminders')->assertSuccessful();

    expect($user->notifications()->count())->toBe(0);
})->with([
    'premium with reminder disabled' => [true, false],
    'free with reminder enabled' => [false, true],
]);
