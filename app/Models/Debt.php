<?php

namespace App\Models;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Observers\DebtObserver;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'counterparty_name', 'amount', 'due_date', 'description', 'reminder_enabled'])]
#[ObservedBy(DebtObserver::class)]
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DebtType::class,
            'status' => DebtStatus::class,
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'due_date' => 'date',
            'reminder_enabled' => 'boolean',
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
     * @return HasMany<DebtPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /**
     * @param  Builder<Debt>  $query
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->where('status', DebtStatus::Unpaid);
    }

    /**
     * Hitung ulang sisa utang & status dari total cicilan yang sudah tercatat.
     */
    public function recalculateRemainingAmount(): void
    {
        $paidAmount = $this->exists ? (float) $this->payments()->sum('amount') : 0;

        $this->remaining_amount = max(0, (float) $this->amount - $paidAmount);
        $this->status = (float) $this->remaining_amount > 0 ? DebtStatus::Unpaid : DebtStatus::Paid;
    }

    public function summaryLabel(): string
    {
        return match ($this->type) {
            DebtType::Receivable => "Piutang dari {$this->counterparty_name}",
            DebtType::Payable => "Utang ke {$this->counterparty_name}",
        };
    }

    public function isOverdue(): bool
    {
        return $this->status === DebtStatus::Unpaid
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }
}
