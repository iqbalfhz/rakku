<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\Transfer;

class TransferObserver
{
    public function created(Transfer $transfer): void
    {
        $this->applyBalances($transfer->from_account_id, $transfer->to_account_id, (float) $transfer->amount);
    }

    /**
     * Batalkan efek saldo versi lama, lalu terapkan versi baru.
     */
    public function updated(Transfer $transfer): void
    {
        if (! $transfer->wasChanged(['from_account_id', 'to_account_id', 'amount'])) {
            return;
        }

        $this->applyBalances(
            $transfer->getOriginal('from_account_id'),
            $transfer->getOriginal('to_account_id'),
            -(float) $transfer->getOriginal('amount'),
        );

        $this->applyBalances($transfer->from_account_id, $transfer->to_account_id, (float) $transfer->amount);
    }

    public function deleted(Transfer $transfer): void
    {
        $this->applyBalances($transfer->from_account_id, $transfer->to_account_id, -(float) $transfer->amount);
    }

    private function applyBalances(int $fromAccountId, int $toAccountId, float $amount): void
    {
        Account::adjustBalance($fromAccountId, -$amount);
        Account::adjustBalance($toAccountId, $amount);
    }
}
