<?php

namespace App\Support\Sync;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin sinkronisasi antara buku di server dan salinannya di ponsel.
 *
 * Akun dan kategori dikirim utuh setiap kali karena jumlahnya sedikit dan hanya
 * bisa diubah lewat web. Transaksi dikirim sebagai selisih, lengkap dengan baris
 * yang sudah dihapus, supaya penghapusan ikut sampai ke ponsel.
 */
class LedgerSync
{
    /**
     * Berapa lama transaksi lama ikut dikirim saat ponsel menarik untuk pertama kali.
     */
    public const int INITIAL_MONTHS = 12;

    /**
     * @return array{server_time: string, accounts: list<array<string, mixed>>, categories: list<array<string, mixed>>, transactions: list<array<string, mixed>>}
     */
    public function pull(Book $book, ?CarbonImmutable $since): array
    {
        $serverTime = CarbonImmutable::now();

        return [
            'server_time' => $serverTime->utc()->toIso8601ZuluString(),
            'accounts' => $book->accounts()->get()->map($this->accountPayload(...))->all(),
            'categories' => $book->categories()->get()->map($this->categoryPayload(...))->all(),
            'transactions' => $this->changedTransactions($book, $since)->map($this->transactionPayload(...))->all(),
        ];
    }

    /**
     * Terima perubahan dari ponsel. Baris dikenali lewat public_id yang dibuat ponsel,
     * dan yang menang adalah versi dengan updated_at paling baru.
     *
     * @param  list<array<string, mixed>>  $transactions
     * @return array{applied: int, skipped: int}
     */
    public function push(Book $book, array $transactions): array
    {
        $applied = 0;
        $skipped = 0;

        DB::transaction(function () use ($book, $transactions, &$applied, &$skipped): void {
            foreach ($transactions as $payload) {
                $this->applyIncoming($book, $payload) ? $applied++ : $skipped++;
            }
        });

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function changedTransactions(Book $book, ?CarbonImmutable $since): Collection
    {
        return $book->transactions()
            ->withTrashed()
            ->with(['account', 'category'])
            ->when(
                $since === null,
                fn (Builder $query) => $query->whereNull('deleted_at')
                    ->where('transaction_date', '>=', today()->subMonths(self::INITIAL_MONTHS)),
                fn (Builder $query) => $query->where('updated_at', '>', $since->setTimezone(config('app.timezone'))),
            )
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyIncoming(Book $book, array $payload): bool
    {
        $existing = $book->transactions()->withTrashed()->where('public_id', $payload['public_id'])->first();

        if ($existing?->trashed()) {
            return false;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        if ($payload['is_deleted'] ?? false) {
            $existing?->delete();

            return $existing !== null;
        }

        $account = $book->accounts()->where('public_id', $payload['account_public_id'])->first();

        if ($account === null) {
            return false;
        }

        $attributes = [
            'account_id' => $account->id,
            'category_id' => $book->categories()->where('public_id', $payload['category_public_id'] ?? null)->value('id'),
            'type' => TransactionType::from($payload['type']),
            'amount' => $payload['amount'],
            'description' => $payload['description'] ?? null,
            'transaction_date' => $payload['transaction_date'],
        ];

        if ($existing === null) {
            $transaction = $book->transactions()->make($attributes);
            $transaction->public_id = $payload['public_id'];
            $transaction->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(Account $account): array
    {
        return [
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type->value,
            'current_balance' => (float) $account->current_balance,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category): array
    {
        return [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'type' => $category->type->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(Transaction $transaction): array
    {
        return [
            'public_id' => $transaction->public_id,
            'account_public_id' => $transaction->account?->public_id,
            'category_public_id' => $transaction->category?->public_id,
            'type' => $transaction->type->value,
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'has_receipt' => $transaction->receipt_photo_path !== null,
            'is_deleted' => $transaction->trashed(),
            'updated_at' => $transaction->updated_at->utc()->toIso8601ZuluString(),
        ];
    }
}
