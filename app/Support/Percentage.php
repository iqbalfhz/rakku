<?php

namespace App\Support;

final class Percentage
{
    /**
     * Persentase perubahan dari nilai sebelumnya; null jika nilai sebelumnya nol.
     */
    public static function change(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }
}
