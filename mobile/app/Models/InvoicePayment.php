<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kotak keluar pelunasan invoice: baris di sini dibuang setelah sampai ke server.
 */
class InvoicePayment extends Model
{
    protected $fillable = ['invoice_public_id', 'account_public_id', 'transaction_public_id', 'paid_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }
}
