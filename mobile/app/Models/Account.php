<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'public_id',
        'name',
        'type',
        'initial_balance',
        'current_balance',
        'has_activity',
        'is_dirty',
        'is_deleted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'has_activity' => 'boolean',
            'is_dirty' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'cash' => 'Tunai',
            'bank' => 'Bank',
            'e-wallet' => 'E-Wallet',
            default => 'Lainnya',
        };
    }

    /**
     * Akun yang sudah dipakai tidak boleh dihapus. Yang dibuat di ponsel dan belum
     * terkirim pun bisa saja sudah dipakai transaksi di ponsel ini.
     */
    public function canBeRemoved(): bool
    {
        return ! $this->has_activity
            && ! Transaction::query()->visible()->where('account_public_id', $this->public_id)->exists();
    }
}
