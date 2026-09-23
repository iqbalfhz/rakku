<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['public_id', 'name', 'type', 'current_balance'];

    protected function casts(): array
    {
        return ['current_balance' => 'decimal:2'];
    }
}
