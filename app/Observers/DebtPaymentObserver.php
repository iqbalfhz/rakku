<?php

namespace App\Observers;

use App\Models\DebtPayment;

class DebtPaymentObserver
{
    /**
     * Setiap cicilan dicatat juga sebagai transaksi agar saldo akun & laporan ikut ter-update.
     */
    public function created(DebtPayment $payment): void
    {
        $debt = $payment->debt;

        $transaction = $debt->book->transactions()->create([
            'account_id' => $payment->account_id,
            'type' => $debt->type->paymentTransactionType(),
            'amount' => $payment->amount,
            'description' => "Pembayaran {$debt->type->getLabel()}: {$debt->counterparty_name}",
            'transaction_date' => $payment->payment_date,
        ]);

        $payment->transaction()->associate($transaction)->saveQuietly();

        $debt->touch();
    }

    public function deleted(DebtPayment $payment): void
    {
        $payment->transaction?->delete();

        $payment->debt->touch();
    }
}
