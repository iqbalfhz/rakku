<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('app:generate-recurring-transactions')]
#[Description('Buat transaksi dari jadwal transaksi berulang yang sudah jatuh tempo (khusus pengguna premium)')]
class GenerateRecurringTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = today();
        $generatedCount = 0;

        RecurringTransaction::query()
            ->dueOn($today)
            ->with('book.user.currentSubscription')
            ->chunkById(100, function (Collection $recurringTransactions) use ($today, &$generatedCount): void {
                foreach ($recurringTransactions as $recurringTransaction) {
                    if (! $recurringTransaction->book->user->isPremium()) {
                        continue;
                    }

                    $generatedCount += DB::transaction(
                        fn (): int => $recurringTransaction->generateDueTransactions($today),
                    );
                }
            });

        $this->info("{$generatedCount} transaksi berulang berhasil dibuat.");

        return self::SUCCESS;
    }
}
