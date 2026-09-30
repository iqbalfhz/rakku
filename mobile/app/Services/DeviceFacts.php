<?php

namespace App\Services;

use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\System;
use Throwable;

/**
 * Keterangan tentang ponsel yang sedang menjalankan aplikasi ini.
 *
 * Dibaca sekali lalu diingat: tiap panggilan menyeberang ke sisi Kotlin, dan
 * isinya tidak berubah selama aplikasi hidup.
 *
 * Tidak ada satu pun yang melempar di sini. Kedua pemakainya — pelapor kerusakan
 * dan pemberian nama perangkat saat masuk — harus tetap jalan di runtime yang
 * jembatannya tidak ada sama sekali, misalnya saat diuji di laptop.
 */
class DeviceFacts
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $info = null;

    /**
     * Merek dan model, mis. "Samsung SM-A536E".
     *
     * Build.MANUFACTURER datang huruf kecil semua di Android; di iOS nilainya "Apple".
     */
    public function name(): ?string
    {
        $info = $this->info();

        $parts = array_filter([
            isset($info['manufacturer']) ? ucfirst((string) $info['manufacturer']) : null,
            $info['model'] ?? null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    public function operatingSystem(): ?string
    {
        $info = $this->info();

        $name = trim(($info['operatingSystem'] ?? '').' '.($info['osVersion'] ?? ''));

        return $name === '' ? null : $name;
    }

    public function sdkVersion(): ?string
    {
        $sdk = $this->info()['androidSDKVersion'] ?? null;

        return $sdk === null ? null : (string) $sdk;
    }

    public function isVirtual(): ?bool
    {
        $virtual = $this->info()['isVirtual'] ?? null;

        return $virtual === null ? null : (bool) $virtual;
    }

    public function webViewVersion(): ?string
    {
        return $this->info()['webViewVersion'] ?? null;
    }

    public function language(): ?string
    {
        return $this->info()['language'] ?? null;
    }

    /**
     * Pengenal yang membedakan satu perangkat dari yang lain.
     *
     * Bukan alamat MAC: sejak Android 6 semua aplikasi hanya menerima
     * 02:00:00:00:00:00, dan Google menggolongkannya sebagai pengenal permanen
     * yang tidak bisa direset — memintanya adalah alasan penolakan di Play Store.
     * Yang dipakai di sini khusus per aplikasi dan ikut terhapus saat reset pabrik.
     */
    public function id(): ?string
    {
        try {
            $id = Device::getId();
        } catch (Throwable) {
            return null;
        }

        // Jembatannya menjawab "unknown" saat pengenalnya tidak terbaca.
        return $id === null || $id === 'unknown' ? null : $id;
    }

    public function platform(): string
    {
        try {
            return System::isAndroid() ? 'Android' : (System::isIos() ? 'iOS' : 'Lainnya');
        } catch (Throwable) {
            return 'Lainnya';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function info(): array
    {
        if ($this->info !== null) {
            return $this->info;
        }

        try {
            $decoded = json_decode((string) Device::getInfo(), associative: true);
        } catch (Throwable) {
            $decoded = null;
        }

        return $this->info = is_array($decoded) ? $decoded : [];
    }
}
