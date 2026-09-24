<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'public_id',
        'account_public_id',
        'category_public_id',
        'type',
        'amount',
        'description',
        'frequency',
        'start_date',
        'next_run_date',
        'end_date',
        'is_active',
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
            'start_date' => 'date',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
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
     * @param  Builder<RecurringTransaction>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * @param  Builder<RecurringTransaction>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'yearly' => 'Tahunan',
            default => $this->frequency,
        };
    }

    /**
     * Jadwal yang baru dibuat di ponsel belum punya tanggal jalan dari server.
     */
    public function nextRunLabel(): string
    {
        if (! $this->is_active) {
            return 'Dijeda';
        }

        return $this->next_run_date === null
            ? 'Mulai '.$this->start_date->translatedFormat('j M Y')
            : 'Berikutnya '.$this->next_run_date->translatedFormat('j M Y');
    }
}
