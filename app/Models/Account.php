<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Observers\AccountObserver;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'initial_balance'])]
#[ObservedBy(AccountObserver::class)]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transfer, $this>
     */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_account_id');
    }

    /**
     * @return HasMany<Transfer, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_account_id');
    }

    /**
     * @return HasMany<RecurringTransaction, $this>
     */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * @return HasMany<DebtPayment, $this>
     */
    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /**
     * Ubah saldo ter-cache secara atomik di database (positif menambah, negatif mengurangi).
     */
    public static function adjustBalance(int $accountId, float $amount): void
    {
        static::query()->whereKey($accountId)->increment('current_balance', round($amount, 2));
    }

    /**
     * Akun yang sudah punya riwayat tidak boleh dihapus agar saldo akun lain tetap konsisten.
     */
    public function hasActivity(): bool
    {
        return $this->transactions()->exists()
            || $this->outgoingTransfers()->exists()
            || $this->incomingTransfers()->exists()
            || $this->recurringTransactions()->exists()
            || $this->debtPayments()->exists();
    }
}
