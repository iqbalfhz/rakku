<?php

namespace App\Models;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Observers\RecurringTransactionObserver;
use Carbon\CarbonInterface;
use Database\Factories\RecurringTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'account_id',
    'category_id',
    'type',
    'amount',
    'description',
    'frequency',
    'start_date',
    'next_run_date',
    'end_date',
    'is_active',
])]
#[ObservedBy(RecurringTransactionObserver::class)]
class RecurringTransaction extends Model
{
    /** @use HasFactory<RecurringTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => RecurringFrequency::class,
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<RecurringTransaction>  $query
     */
    #[Scope]
    protected function dueOn(Builder $query, CarbonInterface $date): void
    {
        $query->where('is_active', true)->whereDate('next_run_date', '<=', $date->toDateString());
    }

    /**
     * Buat transaksi untuk setiap jadwal yang sudah lewat (termasuk yang terlewat),
     * lalu majukan jadwal berikutnya. Mengembalikan jumlah transaksi yang dibuat.
     */
    public function generateDueTransactions(CarbonInterface $today): int
    {
        if (! $this->is_active) {
            return 0;
        }

        $generated = 0;

        while ($this->next_run_date->lte($today) && $this->isScheduledOn($this->next_run_date)) {
            $this->transactions()->forceCreate([
                'book_id' => $this->book_id,
                'account_id' => $this->account_id,
                'category_id' => $this->category_id,
                'type' => $this->type,
                'amount' => $this->amount,
                'description' => $this->description,
                'transaction_date' => $this->next_run_date,
            ]);

            $this->next_run_date = $this->frequency->nextDate($this->next_run_date, $this->start_date);
            $generated++;
        }

        if (! $this->isScheduledOn($this->next_run_date)) {
            $this->is_active = false;
        }

        $this->save();

        return $generated;
    }

    private function isScheduledOn(CarbonInterface $date): bool
    {
        return $this->end_date === null || $date->lte($this->end_date);
    }
}
