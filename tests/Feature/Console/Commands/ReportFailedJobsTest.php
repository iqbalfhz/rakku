<?php

use App\Console\Commands\ReportFailedJobs;
use App\Models\User;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Str;

/**
 * Catat satu job gagal seperti yang dilakukan queue worker saat job melempar exception.
 */
function logFailedJob(string $displayName = 'App\Mail\InvoiceMail'): void
{
    app(FailedJobProviderInterface::class)->log(
        'database',
        'default',
        json_encode(['uuid' => (string) Str::uuid(), 'displayName' => $displayName]),
        new RuntimeException('SMTP menolak koneksi'),
    );
}

it('notifies every admin about jobs that failed recently', function () {
    $admin = User::factory()->admin()->create();
    logFailedJob();
    logFailedJob();
    logFailedJob('App\Jobs\ExportTransactions');

    $this->artisan('app:report-failed-jobs')
        ->expectsOutputToContain('3 job gagal dilaporkan ke 1 admin')
        ->assertSuccessful();

    $notification = $admin->notifications()->sole();

    expect($notification->data['title'])->toBe('3 job queue gagal')
        ->and($notification->data['body'])->toContain('InvoiceMail (2x)')
        ->and($notification->data['body'])->toContain('ExportTransactions (1x)')
        ->and($notification->data['body'])->toContain('queue:retry all');
});

it('stays quiet when no job has failed', function () {
    $admin = User::factory()->admin()->create();

    $this->artisan('app:report-failed-jobs')
        ->expectsOutputToContain('Tidak ada job yang gagal')
        ->assertSuccessful();

    expect($admin->notifications()->count())->toBe(0);
});

it('ignores failures older than the reported window', function () {
    $admin = User::factory()->admin()->create();
    $this->travelTo(now()->subHours(ReportFailedJobs::REPORTED_HOURS + 1));
    logFailedJob();
    $this->travelBack();

    $this->artisan('app:report-failed-jobs')
        ->expectsOutputToContain('Tidak ada job yang gagal')
        ->assertSuccessful();

    expect($admin->notifications()->count())->toBe(0);
});

it('does not notify regular users', function () {
    $member = User::factory()->create();
    logFailedJob();

    $this->artisan('app:report-failed-jobs')->assertSuccessful();

    expect($member->notifications()->count())->toBe(0);
});
