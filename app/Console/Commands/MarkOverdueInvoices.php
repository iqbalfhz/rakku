<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:mark-overdue-invoices')]
#[Description('Tandai invoice terkirim yang sudah melewati jatuh tempo sebagai telat')]
class MarkOverdueInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updatedCount = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_date', '<', today())
            ->update(['status' => InvoiceStatus::Overdue]);

        $this->info("{$updatedCount} invoice ditandai telat.");

        return self::SUCCESS;
    }
}
