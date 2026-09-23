<?php

namespace App\Support;

/**
 * Format rupiah untuk aplikasi ponsel.
 *
 * Ditulis manual, bukan lewat Number::currency(), karena PHP yang ditanam
 * NativePHP ke dalam ponsel tidak membawa ekstensi intl.
 */
final class Rupiah
{
    private const string THOUSANDS_SEPARATOR = '.';

    private const string DECIMAL_SEPARATOR = ',';

    public static function format(float|int $amount): string
    {
        return 'Rp '.number_format($amount, 0, self::DECIMAL_SEPARATOR, self::THOUSANDS_SEPARATOR);
    }
}
