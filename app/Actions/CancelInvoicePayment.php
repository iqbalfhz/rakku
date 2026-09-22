<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CancelInvoicePayment
{
    /**
     * Batalkan pelunasan: hapus transaksi pemasukannya dan kembalikan status invoice.
     */
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $transaction = $invoice->transaction;

            $invoice->transaction()->dissociate();
            $invoice->status = $invoice->due_date->lt(today()) ? InvoiceStatus::Overdue : InvoiceStatus::Sent;
            $invoice->save();

            $transaction?->delete();

            return $invoice;
        });
    }
}
