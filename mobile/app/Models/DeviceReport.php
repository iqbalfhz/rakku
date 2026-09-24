<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kabar kerusakan yang menunggu dikirim ke server.
 */
class DeviceReport extends Model
{
    protected $fillable = ['kind', 'message', 'fingerprint', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
