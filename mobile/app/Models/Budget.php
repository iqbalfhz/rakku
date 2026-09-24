<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    /**
     * Ambang peringatan yang dipakai kalau pengguna belum menentukan sendiri.
     */
    public const int DEFAULT_ALERT_THRESHOLD = 80;

    protected $fillable = [
        'public_id',
        'category_public_id',
        'amount',
        'alert_enabled',
        'alert_threshold_percent',
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
            'alert_enabled' => 'boolean',
            'alert_threshold_percent' => 'integer',
            'server_updated_at' => 'datetime',
            'is_dirty' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_public_id', 'public_id');
    }

    /**
     * @param  Builder<Budget>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * @param  Builder<Budget>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    public function usagePercent(float $spent): float
    {
        return (float) $this->amount > 0 ? round($spent / (float) $this->amount * 100, 1) : 0;
    }

    public function alertThreshold(): int
    {
        return $this->alert_threshold_percent ?? self::DEFAULT_ALERT_THRESHOLD;
    }
}
