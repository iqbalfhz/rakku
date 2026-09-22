<?php

use App\Console\Commands\GenerateRecurringTransactions;
use App\Console\Commands\MarkOverdueInvoices;
use App\Console\Commands\SendDebtReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateRecurringTransactions::class)->dailyAt('00:05')->withoutOverlapping();
Schedule::command(MarkOverdueInvoices::class)->dailyAt('00:10');
Schedule::command(SendDebtReminders::class)->dailyAt('08:00');
