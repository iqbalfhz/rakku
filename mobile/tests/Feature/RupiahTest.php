<?php

use App\Support\Rupiah;

/**
 * PHP di dalam ponsel tidak punya ekstensi intl, jadi format ini harus
 * dihitung sendiri. Spasi biasa di sini juga disengaja: Number::currency()
 * memakai spasi tak-putus, dan test ini menangkap kalau ada yang kembali ke sana.
 */
it('writes rupiah the Indonesian way without needing intl', function (float $amount, string $expected) {
    expect(Rupiah::format($amount))->toBe($expected);
})->with([
    'ribuan' => [25_000, 'Rp 25.000'],
    'jutaan' => [1_250_000, 'Rp 1.250.000'],
    'nol' => [0, 'Rp 0'],
    'pecahan dibulatkan' => [1_500.75, 'Rp 1.501'],
    'negatif' => [-40_000, 'Rp -40.000'],
]);
