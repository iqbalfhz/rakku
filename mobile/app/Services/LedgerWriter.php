<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menulis catatan di dalam ponsel, tanpa perlu sinyal.
 *
 * Saldo akun ikut disesuaikan seketika supaya angka di layar tidak berbohong
 * sampai sinkron berikutnya. Setelah sinkron, angka dari server yang berlaku.
 */
class LedgerWriter
{
    /**
     * @param  array{account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, transaction_date: string}  $data
     */
    public function record(array $data): Transaction
    {
        return DB::transaction(function () use ($data): Transaction {
            $transaction = Transaction::query()->create([
                'public_id' => Str::lower((string) Str::ulid()),
                'account_public_id' => $data['account_public_id'],
                'category_public_id' => $data['category_public_id'] ?? null,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'is_dirty' => true,
                'is_deleted' => false,
            ]);

            $this->adjustBalance($transaction->account_public_id, $transaction->signedAmount());

            return $transaction;
        });
    }

    /**
     * Penghapusan disimpan dulu sebagai penanda, karena server perlu diberi tahu.
     */
    public function remove(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $this->adjustBalance($transaction->account_public_id, -$transaction->signedAmount());

            $transaction->update(['is_deleted' => true, 'is_dirty' => true]);
        });
    }

    private function adjustBalance(string $accountPublicId, float $amount): void
    {
        Account::query()
            ->where('public_id', $accountPublicId)
            ->increment('current_balance', $amount);
    }
}
