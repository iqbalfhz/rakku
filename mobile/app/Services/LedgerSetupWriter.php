<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Menulis akun dan kategori di dalam ponsel.
 *
 * Keduanya adalah rangka buku kas: tanpa akun tidak ada tempat mencatat, tanpa
 * kategori tidak ada cara mengelompokkan. Dulu keduanya hanya bisa diurus lewat
 * web, yang berarti mencatat "Bensin" pertama kali memaksa membuka browser.
 */
class LedgerSetupWriter
{
    /**
     * @param  array{name: string, type: string, initial_balance: float}  $data
     */
    public function recordAccount(array $data): Account
    {
        return Account::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            'name' => $data['name'],
            'type' => $data['type'],
            'initial_balance' => $data['initial_balance'],
            'current_balance' => $data['initial_balance'],
            'has_activity' => false,
            'is_dirty' => true,
            'is_deleted' => false,
        ]);
    }

    /**
     * Mengubah saldo awal ikut menggeser saldo berjalan sebesar selisihnya, sama
     * seperti yang dilakukan server, supaya angka di layar tidak berbohong sampai sinkron.
     *
     * @param  array{name: string, type: string, initial_balance: float}  $data
     */
    public function reviseAccount(Account $account, array $data): Account
    {
        $difference = $data['initial_balance'] - (float) $account->initial_balance;

        $account->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'initial_balance' => $data['initial_balance'],
            'current_balance' => (float) $account->current_balance + $difference,
            'is_dirty' => true,
        ]);

        return $account;
    }

    public function removeAccount(Account $account): bool
    {
        if (! $account->canBeRemoved()) {
            return false;
        }

        $account->update(['is_deleted' => true, 'is_dirty' => true]);

        return true;
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function recordCategory(array $data): Category
    {
        return Category::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            'name' => $data['name'],
            'type' => $data['type'],
            'is_dirty' => true,
            'is_deleted' => false,
        ]);
    }

    /**
     * @param  array{name: string, type: string}  $data
     */
    public function reviseCategory(Category $category, array $data): Category
    {
        $category->update(['name' => $data['name'], 'type' => $data['type'], 'is_dirty' => true]);

        return $category;
    }

    /**
     * Kategori yang dihapus melepaskan transaksinya menjadi tanpa kategori, sama seperti di server.
     */
    public function removeCategory(Category $category): void
    {
        $category->update(['is_deleted' => true, 'is_dirty' => true]);
    }
}
