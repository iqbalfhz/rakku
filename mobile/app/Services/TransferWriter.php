<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Memindahkan uang antar akun milik sendiri, tanpa perlu sinyal.
 *
 * Bukan pemasukan dan bukan pengeluaran: yang berubah hanya di akun mana uang itu
 * berada. Dicatat sebagai dua transaksi terpisah, laporan laba-rugi akan berbohong.
 */
class TransferWriter
{
    /**
     * @param  array{from_account_public_id: string, to_account_public_id: string, amount: float, description?: string|null, transfer_date: string}  $data
     */
    public function record(array $data): Transfer
    {
        return DB::transaction(function () use ($data): Transfer {
            $transfer = Transfer::query()->create([
                'public_id' => Str::lower((string) Str::ulid()),
                'from_account_public_id' => $data['from_account_public_id'],
                'to_account_public_id' => $data['to_account_public_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'is_dirty' => true,
                'is_deleted' => false,
            ]);

            $this->shiftBalances($transfer, (float) $transfer->amount);

            return $transfer;
        });
    }

    /**
     * Efek saldo versi lama dibatalkan dulu sebelum versi baru diterapkan, supaya
     * pindah akun atau ganti nominal tidak meninggalkan selisih.
     *
     * @param  array{from_account_public_id: string, to_account_public_id: string, amount: float, description?: string|null, transfer_date: string}  $data
     */
    public function revise(Transfer $transfer, array $data): Transfer
    {
        return DB::transaction(function () use ($transfer, $data): Transfer {
            $this->shiftBalances($transfer, -(float) $transfer->amount);

            $transfer->update([
                'from_account_public_id' => $data['from_account_public_id'],
                'to_account_public_id' => $data['to_account_public_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'transfer_date' => $data['transfer_date'],
                'is_dirty' => true,
            ]);

            $this->shiftBalances($transfer, (float) $transfer->amount);

            return $transfer;
        });
    }

    /**
     * Pembatalan disimpan dulu sebagai penanda, karena server perlu diberi tahu.
     */
    public function remove(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $this->shiftBalances($transfer, -(float) $transfer->amount);

            $transfer->update(['is_deleted' => true, 'is_dirty' => true]);
        });
    }

    private function shiftBalances(Transfer $transfer, float $amount): void
    {
        Account::query()->where('public_id', $transfer->from_account_public_id)->decrement('current_balance', $amount);
        Account::query()->where('public_id', $transfer->to_account_public_id)->increment('current_balance', $amount);
    }
}
