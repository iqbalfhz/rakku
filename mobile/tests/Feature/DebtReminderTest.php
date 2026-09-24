<?php

use App\Models\Account;
use App\Models\Debt;
use App\Services\DebtReminderScheduler;
use App\Services\DebtWriter;
use App\Services\PlanGate;
use App\Services\TokenStore;
use Carbon\CarbonImmutable;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

beforeEach(function () {
    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');

    app(PlanGate::class)->remember(['is_premium' => true, 'expires_at' => null]);

    Account::query()->create(['public_id' => '01m3kas', 'name' => 'Kas Laci', 'type' => 'cash', 'current_balance' => 0]);

    $this->travelTo('2026-10-01 07:00');
});

/**
 * Utang yang jatuh tempo pada tanggal tertentu.
 */
function debtDueOn(string $dueDate, array $attributes = []): Debt
{
    return Debt::query()->create([
        'public_id' => '01m3utang',
        'type' => 'receivable',
        'counterparty_name' => 'Bu Rina',
        'amount' => 100_000,
        'remaining_amount' => 100_000,
        'status' => 'unpaid',
        'due_date' => $dueDate,
        'reminder_enabled' => true,
        ...$attributes,
    ]);
}

it('puts a reminder in the phone three days, one day, and on the due date', function () {
    LocalNotifications::spy();
    debtDueOn('2026-10-20');

    app(DebtReminderScheduler::class)->refresh();

    LocalNotifications::shouldHaveReceived('cancelAll');
    LocalNotifications::shouldHaveReceived('schedule')->times(3);
});

it('words the reminder so it can be read at a glance', function () {
    LocalNotifications::spy();
    debtDueOn('2026-10-20');

    app(DebtReminderScheduler::class)->refresh();

    LocalNotifications::shouldHaveReceived('schedule')->withArgs(function (array $options): bool {
        return $options['title'] === 'Piutang dari Bu Rina jatuh tempo hari ini'
            && $options['body'] === 'Sisa Rp 100.000.'
            && $options['at'] === CarbonImmutable::parse('2026-10-20 08:00')->timestamp;
    });
});

it('skips the reminders whose moment has already passed', function () {
    LocalNotifications::spy();
    debtDueOn('2026-10-02');

    app(DebtReminderScheduler::class)->refresh();

    // Tinggal H-1 dan hari-H; yang H-3 sudah lewat.
    LocalNotifications::shouldHaveReceived('schedule')->twice();
});

it('leaves a settled debt without reminders', function () {
    LocalNotifications::spy();
    debtDueOn('2026-10-20', ['status' => 'paid', 'remaining_amount' => 0]);

    app(DebtReminderScheduler::class)->refresh();

    LocalNotifications::shouldNotHaveReceived('schedule');
});

it('respects a debt whose reminder was switched off', function () {
    LocalNotifications::spy();
    debtDueOn('2026-10-20', ['reminder_enabled' => false]);

    app(DebtReminderScheduler::class)->refresh();

    LocalNotifications::shouldNotHaveReceived('schedule');
});

it('clears the reminders for an account that is no longer premium', function () {
    LocalNotifications::spy();
    app(PlanGate::class)->remember(['is_premium' => false, 'expires_at' => null]);
    debtDueOn('2026-10-20');

    app(DebtReminderScheduler::class)->refresh();

    LocalNotifications::shouldHaveReceived('cancelAll');
    LocalNotifications::shouldNotHaveReceived('schedule');
});

it('rebuilds the reminders as soon as a debt is written down', function () {
    LocalNotifications::spy();

    app(DebtWriter::class)->record([
        'type' => 'receivable',
        'counterparty_name' => 'Bu Rina',
        'amount' => 100_000,
        'due_date' => '2026-10-20',
    ]);

    LocalNotifications::shouldHaveReceived('schedule')->times(3);
});

it('takes the reminders away once the debt is fully paid', function () {
    LocalNotifications::spy();
    $debt = debtDueOn('2026-10-20');

    app(DebtWriter::class)->pay($debt, [
        'account_public_id' => '01m3kas',
        'amount' => 100_000,
        'payment_date' => '2026-10-01',
    ]);

    expect($debt->fresh()->status)->toBe('paid');

    LocalNotifications::shouldNotHaveReceived('schedule');
});
