<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-exports {--days= : Umur file export yang masih disimpan, dalam hari}')]
#[Description('Hapus file hasil export yang sudah lama beserta catatannya agar penyimpanan tidak penuh')]
class PruneExports extends Command
{
    /**
     * Filament sengaja tidak menghapus file export, jadi pembersihannya diurus di sini.
     */
    public const int KEEP_DAYS = 90;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? self::KEEP_DAYS));
        $prunedCount = 0;

        Export::query()
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (Export $export) use (&$prunedCount): void {
                $export->deleteFileDirectory();
                $export->delete();

                $prunedCount++;
            });

        $this->info("{$prunedCount} export lebih tua dari {$days} hari dihapus.");

        return self::SUCCESS;
    }
}
