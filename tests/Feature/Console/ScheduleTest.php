<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules the daily jobs and processes the queue every minute', function () {
    $scheduledCommands = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event): array => [
            trim(str($event->command)->after('artisan'), " '\"") => $event->expression,
        ]);

    expect($scheduledCommands->all())->toMatchArray([
        'app:generate-recurring-transactions' => '5 0 * * *',
        'app:mark-overdue-invoices' => '10 0 * * *',
        'app:send-debt-reminders' => '0 8 * * *',
        'app:backup-files' => '30 2 * * *',
        'app:report-failed-jobs' => '0 7 * * *',
        'queue:work --stop-when-empty --max-time=55 --tries=3 --backoff=60' => '* * * * *',
    ]);
});
