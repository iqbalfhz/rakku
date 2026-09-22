<?php

namespace App\Support;

final class WhatsApp
{
    private const string COUNTRY_CODE = '62';

    /**
     * Link wa.me untuk membuka chat dengan pesan yang sudah terisi.
     */
    public static function chatUrl(string $phone, string $message): string
    {
        return 'https://wa.me/'.self::normalizePhone($phone).'?text='.rawurlencode($message);
    }

    /**
     * Ubah nomor lokal (0812..., +62 812-..., 812...) ke format internasional tanpa simbol: 62812...
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return match (true) {
            str_starts_with($digits, self::COUNTRY_CODE) => $digits,
            str_starts_with($digits, '0') => self::COUNTRY_CODE.substr($digits, 1),
            default => self::COUNTRY_CODE.$digits,
        };
    }
}
