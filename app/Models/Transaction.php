<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use Carbon\CarbonInterface;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'account_id',
    'category_id',
    'type',
    'amount',
    'description',
    'receipt_photo_path',
    'transaction_date',
    'recurring_transaction_id',
])]
#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const string RECEIPT_DIRECTORY = 'receipts';

    public static function receiptDisk(): string
    {
        return config('filesystems.receipts');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<RecurringTransaction, $this>
     */
    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    /**
     * @return HasOne<DebtPayment, $this>
     */
    public function debtPayment(): HasOne
    {
        return $this->hasOne(DebtPayment::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Nilai transaksi terhadap saldo akun: positif untuk pemasukan, negatif untuk pengeluaran.
     */
    public function signedAmount(): float
    {
        return $this->type->balanceDirection() * (float) $this->amount;
    }

    /**
     * Transaksi hasil cicilan utang-piutang atau pelunasan invoice hanya boleh diubah dari sumbernya.
     */
    public function isLocked(): bool
    {
        return $this->debtPayment !== null || $this->invoice !== null;
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, TransactionType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Rentang dibandingkan sebagai datetime agar konsisten di SQLite (tanggal tersimpan beserta jam) maupun MySQL.
     *
     * @param  Builder<Transaction>  $query
     */
    #[Scope]
    protected function betweenDates(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->whereBetween('transaction_date', [$from->startOfDay(), $until->endOfDay()]);
    }
}
