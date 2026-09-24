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
     * Antrean ke server dimatikan untuk catatan yang di sana lahir dari baris lain,
     * misalnya transaksi hasil cicilan utang — kalau ikut didorong, ia akan tercatat dua kali.
     *
     * @param  array{public_id?: string, account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, transaction_date: string, receipt_local_path?: string|null}  $data
     */
    public function record(array $data, bool $queueForServer = true): Transaction
    {
        return DB::transaction(function () use ($data, $queueForServer): Transaction {
            $transaction = Transaction::query()->create([
                'public_id' => $data['public_id'] ?? Str::lower((string) Str::ulid()),
                'account_public_id' => $data['account_public_id'],
                'category_public_id' => $data['category_public_id'] ?? null,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'receipt_local_path' => $data['receipt_local_path'] ?? null,
                'is_dirty' => $queueForServer,
                'is_deleted' => false,
            ]);

            $this->adjustBalance($transaction->account_public_id, $transaction->signedAmount());

            return $transaction;
        });
    }

    /**
     * Ubah catatan yang sudah ada. Saldo lama dibatalkan dulu sebelum yang baru
     * diterapkan, supaya pindah akun atau ganti nominal tidak meninggalkan selisih.
     *
     * @param  array{account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, transaction_date: string, receipt_local_path?: string|null}  $data
     */
    public function revise(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data): Transaction {
            $this->adjustBalance($transaction->account_public_id, -$transaction->signedAmount());

            $transaction->fill([
                'account_public_id' => $data['account_public_id'],
                'category_public_id' => $data['category_public_id'] ?? null,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'is_dirty' => true,
            ]);

            if (array_key_exists('receipt_local_path', $data) && $data['receipt_local_path'] !== null) {
                $transaction->receipt_local_path = $data['receipt_local_path'];
                $transaction->has_receipt = false;
            }

            $transaction->save();

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
