<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

#[Signature('app:report-failed-jobs')]
#[Description('Beri tahu admin bila ada job queue yang gagal, mis. email invoice yang tidak terkirim')]
class ReportFailedJobs extends Command
{
    /**
     * Rentang waktu yang dilaporkan, sepanjang jarak antar-jadwal command ini.
     */
    public const int REPORTED_HOURS = 24;

    /**
     * Execute the console command.
     */
    public function handle(FailedJobProviderInterface $failedJobs): int
    {
        $since = now()->subHours(self::REPORTED_HOURS);

        $recentFailures = collect($failedJobs->all())
            ->filter(fn (object $job): bool => Date::parse($job->failed_at)->gte($since));

        if ($recentFailures->isEmpty()) {
            $this->info('Tidak ada job yang gagal dalam '.self::REPORTED_HOURS.' jam terakhir.');

            return self::SUCCESS;
        }

        $admins = User::query()->where('is_admin', true)->get();

        $admins->each(fn (User $admin) => Notification::make()
            ->danger()
            ->title("{$recentFailures->count()} job queue gagal")
            ->body($this->summarize($recentFailures))
            ->sendToDatabase($admin));

        $this->warn("{$recentFailures->count()} job gagal dilaporkan ke {$admins->count()} admin.");

        return self::SUCCESS;
    }

    /**
     * Sebutkan jenis job yang gagal beserta jumlahnya agar penyebabnya langsung terbaca.
     *
     * @param  Collection<int, object>  $failures
     */
    private function summarize(Collection $failures): string
    {
        $perJob = $failures
            ->countBy(fn (object $job): string => class_basename(json_decode($job->payload)->displayName ?? 'Job'))
            ->map(fn (int $count, string $job): string => "{$job} ({$count}x)")
            ->implode(', ');

        return "{$perJob}. Perbaiki penyebabnya, lalu jalankan `php artisan queue:retry all` di server.";
    }
}
