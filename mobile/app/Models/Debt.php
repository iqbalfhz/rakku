<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = [
        'public_id',
        'type',
        'counterparty_name',
        'amount',
        'remaining_amount',
        'due_date',
        'description',
        'status',
        'reminder_enabled',
        'server_updated_at',
        'is_dirty',
        'is_deleted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'due_date' => 'date',
            'reminder_enabled' => 'boolean',
            'server_updated_at' => 'datetime',
            'is_dirty' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DebtPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class, 'debt_public_id', 'public_id');
    }

    /**
     * Yang masih menunggu giliran dikirim ke server.
     *
     * @param  Builder<Debt>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * Yang tampil di layar: semua kecuali yang sudah dihapus tapi belum sempat dikirim.
     *
     * @param  Builder<Debt>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    /**
     * @param  Builder<Debt>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', 'unpaid');
    }

    public function isReceivable(): bool
    {
        return $this->type === 'receivable';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function typeLabel(): string
    {
        return $this->isReceivable() ? 'Piutang' : 'Utang';
    }

    public function summaryLabel(): string
    {
        return $this->isReceivable()
            ? "Piutang dari {$this->counterparty_name}"
            : "Utang ke {$this->counterparty_name}";
    }
}
