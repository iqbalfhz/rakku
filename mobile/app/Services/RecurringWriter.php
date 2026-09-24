<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use Illuminate\Support\Str;

/**
 * Menulis jadwal transaksi berulang di dalam ponsel.
 *
 * Yang ditulis di sini hanya aturannya. Transaksinya sendiri dibuat penjadwal di
 * server setiap hari — ponsel yang tertutup tidak bisa bangun sendiri untuk itu.
 * Jadwal yang dibuat offline tetap aman: begitu tersinkron, server ikut membuat
 * transaksi untuk tanggal-tanggal yang sempat terlewat.
 */
class RecurringWriter
{
    /**
     * @param  array{account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, frequency: string, start_date: string, end_date?: string|null, is_active?: bool}  $data
     */
    public function record(array $data): RecurringTransaction
    {
        return RecurringTransaction::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            ...$this->attributes($data),
            'is_dirty' => true,
            'is_deleted' => false,
        ]);
    }

    /**
     * @param  array{account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, frequency: string, start_date: string, end_date?: string|null, is_active?: bool}  $data
     */
    public function revise(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        $recurring->update([...$this->attributes($data), 'is_dirty' => true]);

        return $recurring;
    }

    public function togglePause(RecurringTransaction $recurring): RecurringTransaction
    {
        $recurring->update(['is_active' => ! $recurring->is_active, 'is_dirty' => true]);

        return $recurring;
    }

    /**
     * Penghapusan disimpan dulu sebagai penanda, karena server perlu diberi tahu.
     */
    public function remove(RecurringTransaction $recurring): void
    {
        $recurring->update(['is_deleted' => true, 'is_dirty' => true]);
    }

    /**
     * @param  array{account_public_id: string, category_public_id?: string|null, type: string, amount: float, description?: string|null, frequency: string, start_date: string, end_date?: string|null, is_active?: bool}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'account_public_id' => $data['account_public_id'],
            'category_public_id' => $data['category_public_id'] ?? null,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
