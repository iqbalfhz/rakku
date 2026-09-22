<?php

namespace App\Observers;

use App\Actions\NotifyBudgetUsage;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;

class TransactionObserver
{
    public function __construct(private NotifyBudgetUsage $notifyBudgetUsage) {}

    public function created(Transaction $transaction): void
    {
        Account::adjustBalance($transaction->account_id, $transaction->signedAmount());

        $this->notifyBudgetUsage->handle($transaction);
    }

    /**
     * Batalkan efek saldo versi lama, lalu terapkan versi baru.
     */
    public function updated(Transaction $transaction): void
    {
        if ($transaction->wasChanged(['account_id', 'type', 'amount'])) {
            $originalSignedAmount = $transaction->getOriginal('type')->balanceDirection() * (float) $transaction->getOriginal('amount');

            Account::adjustBalance($transaction->getOriginal('account_id'), -$originalSignedAmount);
            Account::adjustBalance($transaction->account_id, $transaction->signedAmount());
        }

        if ($transaction->wasChanged('receipt_photo_path')) {
            $this->deleteReceipt($transaction->getOriginal('receipt_photo_path'));
        }
    }

    public function deleted(Transaction $transaction): void
    {
        Account::adjustBalance($transaction->account_id, -$transaction->signedAmount());

        $this->deleteReceipt($transaction->receipt_photo_path);
    }

    private function deleteReceipt(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(Transaction::RECEIPT_DISK)->delete($path);
        }
    }
}
