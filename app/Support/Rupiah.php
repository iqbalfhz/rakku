<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Format nominal rupiah tanpa desimal, contoh: Rp 1.500.000.
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
