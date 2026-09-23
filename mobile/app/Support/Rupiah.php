<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Format rupiah yang sama dengan aplikasi web: tanpa angka di belakang koma.
 */
final class Rupiah
{
    public const string CURRENCY = 'IDR';

    public const int DECIMAL_PLACES = 0;

    public static function format(float|int $amount): string
    {
        return Number::currency($amount, self::CURRENCY, precision: self::DECIMAL_PLACES);
    }
}
