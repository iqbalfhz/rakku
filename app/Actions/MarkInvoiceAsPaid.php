<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkInvoiceAsPaid
{
    /**
     * Lunasi invoice dan catat pemasukannya sebagai transaksi pada akun penerima.
     */
    public function handle(Invoice $invoice, Account $account, CarbonInterface $paidAt, ?int $categoryId = null): Invoice
    {
        if (! $invoice->status->isPayable()) {
            throw new InvalidArgumentException("Invoice {$invoice->invoice_number} tidak bisa dilunasi dari status {$invoice->status->getLabel()}.");
        }

        if ($account->book_id !== $invoice->book_id) {
            throw new InvalidArgumentException('Akun penerima harus berada di buku yang sama dengan invoice.');
        }

        return DB::transaction(function () use ($invoice, $account, $paidAt, $categoryId): Invoice {
            $transaction = $invoice->book->transactions()->create([
                'account_id' => $account->id,
                'category_id' => $categoryId,
                'type' => TransactionType::Income,
                'amount' => $invoice->loadMissing('items')->totalAmount(),
                'description' => "Pelunasan invoice {$invoice->invoice_number} - {$invoice->client->name}",
                'transaction_date' => $paidAt,
            ]);

            $invoice->transaction()->associate($transaction);
            $invoice->status = InvoiceStatus::Paid;
            $invoice->save();

            return $invoice;
        });
    }
}
