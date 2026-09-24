<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Menjalankan migrasi pada database di dalam ponsel.
 *
 * Database ini bertahan antar-pembaruan aplikasi, jadi migrasi baru tidak pernah
 * jalan kalau tidak dipanggil dari sini. Dulu kegagalannya ditelan diam-diam —
 * aplikasi tetap terbuka di atas tabel yang setengah jadi, pengguna melihat error
 * yang tidak masuk akal, dan tidak ada seorang pun yang tahu. Sekarang kegagalannya
 * dicatat, ditunjukkan, dan bisa dicoba lagi.
 *
 * Catatannya disimpan sebagai berkas, bukan di tabel settings: kalau migrasi gagal,
 * tabel itu justru yang belum tentu ada.
 */
class DeviceDatabase
{
    private const string FAILURE_FILE = 'device-database-failure.json';

    public function __construct(private DiagnosticsReporter $reporter) {}

    public function migrate(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            $this->rememberFailure($exception);

            return;
        }

        $this->forgetFailure();
    }

    public function hasFailed(): bool
    {
        return $this->failure() !== null;
    }

    /**
     * @return array{message: string, at: string}|null
     */
    public function failure(): ?array
    {
        try {
            if (! Storage::disk('local')->exists(self::FAILURE_FILE)) {
                return null;
            }

            return json_decode(Storage::disk('local')->get(self::FAILURE_FILE), associative: true) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function rememberFailure(Throwable $exception): void
    {
        $message = $exception->getMessage();

        try {
            Storage::disk('local')->put(self::FAILURE_FILE, json_encode([
                'message' => $message,
                'at' => now()->toIso8601String(),
            ]));
        } catch (Throwable) {
            // Ponsel yang penyimpanannya penuh tidak bisa mencatat apa pun; laporannya masih dicoba.
        }

        $this->reporter->report(DiagnosticsReporter::MIGRATION_FAILURE, $message);
    }

    private function forgetFailure(): void
    {
        try {
            Storage::disk('local')->delete(self::FAILURE_FILE);
        } catch (Throwable) {
            // Tidak ada yang bisa dilakukan, dan ini bukan alasan menahan aplikasi.
        }
    }
}
