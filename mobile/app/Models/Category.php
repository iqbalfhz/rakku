<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['public_id', 'name', 'type', 'is_dirty', 'is_deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_dirty' => 'boolean', 'is_deleted' => 'boolean'];
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_deleted', false);
    }

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function typeLabel(): string
    {
        return $this->isIncome() ? 'Pemasukan' : 'Pengeluaran';
    }
}
