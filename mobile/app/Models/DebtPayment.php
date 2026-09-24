<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    protected $fillable = [
        'public_id',
        'debt_public_id',
        'account_public_id',
        'transaction_public_id',
        'amount',
        'payment_date',
        'notes',
        'server_updated_at',
        'is_dirty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'server_updated_at' => 'datetime',
            'is_dirty' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Debt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class, 'debt_public_id', 'public_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_public_id', 'public_id');
    }

    /**
     * Cicilan yang masih menunggu giliran dikirim ke server.
     *
     * @param  Builder<DebtPayment>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }
}
