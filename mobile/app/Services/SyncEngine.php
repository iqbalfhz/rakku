<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menjembatani buku di server dengan salinannya di dalam ponsel.
 *
 * Urutannya selalu dorong dulu, baru tarik: catatan yang dibuat di ponsel harus
 * sampai ke server sebelum ponsel menerima gambaran terbaru, supaya tidak ada
 * yang tertimpa sebelum sempat terkirim.
 */
class SyncEngine
{
    public function __construct(
        private ApiClient $apiClient,
        private TokenStore $tokenStore,
    ) {}

    public function sync(): void
    {
        $this->push();
        $this->pull();
    }

    /**
     * Kirim catatan yang dibuat atau dihapus saat offline.
     */
    public function push(): void
    {
        $pending = Transaction::query()->pending()->get();

        if ($pending->isEmpty()) {
            return;
        }

        $this->apiClient->push($this->bookPublicId(), $pending->map($this->outgoingPayload(...))->all());

        DB::transaction(function () use ($pending): void {
            Transaction::query()->whereIn('id', $pending->where('is_deleted', true)->pluck('id'))->delete();
            Transaction::query()->whereIn('id', $pending->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);
        });
    }

    /**
     * Tarik perubahan sejak sinkron terakhir.
     */
    public function pull(): void
    {
        $payload = $this->apiClient->pull($this->bookPublicId(), $this->tokenStore->lastSyncedAt());

        DB::transaction(function () use ($payload): void {
            $this->replaceAccounts($payload['accounts']);
            $this->replaceCategories($payload['categories']);
            $this->applyTransactions($payload['transactions']);
        });

        $this->tokenStore->rememberSync($payload['server_time']);
    }

    private function bookPublicId(): string
    {
        $bookPublicId = $this->tokenStore->bookPublicId();

        if ($bookPublicId === null) {
            throw new RuntimeException('Belum ada buku yang dipilih.');
        }

        return $bookPublicId;
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingPayload(Transaction $transaction): array
    {
        return [
            'public_id' => $transaction->public_id,
            'account_public_id' => $transaction->account_public_id,
            'category_public_id' => $transaction->category_public_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'is_deleted' => $transaction->is_deleted,
            'updated_at' => $transaction->updated_at->utc()->toIso8601ZuluString(),
        ];
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
            $local = Transaction::query()->where('public_id', $transaction['public_id'])->first();

            // Catatan yang belum terkirim tidak boleh ditimpa jawaban server:
            // isinya lebih baru daripada yang server tahu.
            if ($local?->is_dirty) {
                continue;
            }

            if ($transaction['is_deleted'] ?? false) {
                $local?->delete();

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
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }
    }
}
