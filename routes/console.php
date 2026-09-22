<?php

use App\Console\Commands\BackupFiles;
use App\Console\Commands\GenerateRecurringTransactions;
use App\Console\Commands\MarkOverdueInvoices;
use App\Console\Commands\ReportFailedJobs;
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

// Backup database diurus Coolify; ini menyalin file unggahan yang tidak ikut di dalamnya.
Schedule::command(BackupFiles::class)->dailyAt('02:30')->withoutOverlapping();
Schedule::command(ReportFailedJobs::class)->dailyAt('07:00');

// Pengganti queue worker permanen: email verifikasi/reset kata sandi dan export diproses tiap menit lewat schedule:run.
// Job yang gagal (mis. SMTP gangguan sesaat) dicoba ulang sampai 3 kali dengan jeda 1 menit.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3 --backoff=60')->everyMinute()->withoutOverlapping(2);
