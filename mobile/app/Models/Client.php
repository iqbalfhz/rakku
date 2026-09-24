<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['public_id', 'name', 'email', 'phone', 'address', 'is_dirty'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_dirty' => 'boolean'];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_public_id', 'public_id');
    }

    /**
     * @param  Builder<Client>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dirty', true);
    }
}
