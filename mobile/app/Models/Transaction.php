<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'public_id',
        'account_public_id',
        'category_public_id',
        'type',
        'amount',
        'description',
        'transaction_date',
        'has_receipt',
        'server_updated_at',
        'receipt_local_path',
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
            'transaction_date' => 'date',
            'has_receipt' => 'boolean',
            'server_updated_at' => 'datetime',
            'is_dirty' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_public_id', 'public_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_public_id', 'public_id');
    }

    /**
     * Catatan yang masih menunggu giliran dikirim ke server.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * Yang tampil di layar: semua kecuali yang sudah dihapus tapi belum sempat dikirim.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    /**
     * Foto struk yang sudah dipotret tapi belum sampai ke server.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeWithPendingReceipt(Builder $query): void
    {
        $query->whereNotNull('receipt_local_path')->where('has_receipt', false);
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function signedAmount(): float
    {
        return $this->isIncome() ? (float) $this->amount : -(float) $this->amount;
    }
}
