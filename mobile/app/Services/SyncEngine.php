<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyalin isi buku dari server ke SQLite di dalam ponsel.
 *
 * Akun dan kategori datang utuh setiap kali, jadi yang hilang dari server ikut
 * dihapus di sini. Transaksi datang sebagai selisih, dan baris yang ditandai
 * terhapus dibuang dari ponsel.
 */
class SyncEngine
{
    public function __construct(
        private ApiClient $apiClient,
        private TokenStore $tokenStore,
    ) {}

    /**
     * Tarik perubahan sejak sinkron terakhir.
     *
     * @throws RuntimeException saat ponsel belum memilih buku
     */
    public function pull(): void
    {
        $bookPublicId = $this->tokenStore->bookPublicId();

        if ($bookPublicId === null) {
            throw new RuntimeException('Belum ada buku yang dipilih.');
        }

        $payload = $this->apiClient->pull($bookPublicId, $this->tokenStore->lastSyncedAt());

        DB::transaction(function () use ($payload): void {
            $this->replaceAccounts($payload['accounts']);
            $this->replaceCategories($payload['categories']);
            $this->applyTransactions($payload['transactions']);
        });

        $this->tokenStore->rememberSync($payload['server_time']);
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    private function replaceAccounts(array $accounts): void
    {
        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['public_id' => $account['public_id']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'current_balance' => $account['current_balance'],
                ],
            );
        }

        Account::query()
            ->whereNotIn('public_id', array_column($accounts, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     */
    private function replaceCategories(array $categories): void
    {
        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['public_id' => $category['public_id']],
                ['name' => $category['name'], 'type' => $category['type']],
            );
        }

        Category::query()
            ->whereNotIn('public_id', array_column($categories, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    private function applyTransactions(array $transactions): void
    {
        foreach ($transactions as $transaction) {
            if ($transaction['is_deleted'] ?? false) {
                Transaction::query()->where('public_id', $transaction['public_id'])->delete();

                continue;
            }

            Transaction::query()->updateOrCreate(
                ['public_id' => $transaction['public_id']],
                [
                    'account_public_id' => $transaction['account_public_id'],
                    'category_public_id' => $transaction['category_public_id'],
                    'type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'description' => $transaction['description'],
                    'transaction_date' => $transaction['transaction_date'],
                    'has_receipt' => $transaction['has_receipt'] ?? false,
                    'server_updated_at' => $transaction['updated_at'],
                ],
            );
        }
    }
}
