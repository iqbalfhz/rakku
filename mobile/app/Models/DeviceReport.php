<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kabar kerusakan yang menunggu dikirim ke server.
 */
class DeviceReport extends Model
{
    protected $fillable = ['kind', 'message', 'detail', 'context', 'fingerprint', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'datetime'];
    }
}
