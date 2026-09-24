<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'public_id',
        'client_public_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'notes',
        'status',
        'total_amount',
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
            'issue_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'server_updated_at' => 'datetime',
            'is_dirty' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_public_id', 'public_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_public_id', 'public_id');
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Hanya invoice yang sudah terkirim ke klien yang bisa dilunasi, aturan yang sama seperti di web.
     */
    public function isPayable(): bool
    {
        return in_array($this->status, ['sent', 'overdue'], true);
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date->lt(today());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'sent' => 'Terkirim',
            'paid' => 'Lunas',
            'overdue' => 'Telat',
            default => $this->status,
        };
    }

    /**
     * Invoice yang dibuat offline belum punya nomor: nomornya dibuat server saat tersinkron.
     */
    public function numberLabel(): string
    {
        return $this->invoice_number ?? 'Nomor menyusul';
    }
}
