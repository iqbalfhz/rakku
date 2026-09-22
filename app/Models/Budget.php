<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'amount', 'alert_enabled', 'alert_threshold_percent'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'alert_enabled' => 'boolean',
            'alert_threshold_percent' => 'integer',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Transaksi pada kategori yang dibatasi budget ini.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id', 'category_id');
    }

    /**
     * Sertakan kolom `spent` berisi total pengeluaran kategori pada bulan tertentu.
     *
     * @param  Builder<Budget>  $query
     */
    #[Scope]
    protected function withSpentIn(Builder $query, CarbonInterface $month): void
    {
        $query->withSum([
            'transactions as spent' => fn (Builder $transactions) => $transactions
                ->ofType(TransactionType::Expense)
                ->betweenDates($month->startOfMonth(), $month->endOfMonth()),
        ], 'amount');
    }

    public function spentIn(CarbonInterface $month): float
    {
        return (float) $this->transactions()
            ->ofType(TransactionType::Expense)
            ->betweenDates($month->startOfMonth(), $month->endOfMonth())
            ->sum('amount');
    }

    public function usagePercent(float $spent): float
    {
        return (float) $this->amount > 0 ? round($spent / (float) $this->amount * 100, 1) : 0;
    }
}
