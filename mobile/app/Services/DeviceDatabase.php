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
            $this->makeArtisanRunnable();

            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            $this->rememberFailure($exception);

            return;
        }

        $this->forgetFailure();
    }

    /**
     * Dipanggil saat aplikasi menyala, yaitu pada setiap layar yang dibuka.
     *
     * Memanggil Artisan dari permintaan web memaksa Laravel mendaftarkan seluruh
     * perintah console dan memuat ulang konfigurasinya — terlalu mahal untuk
     * dikerjakan berulang kali, padahal migrasi hanya perlu jalan sesudah aplikasi
     * diperbarui. Tombol "Coba perbaiki" memakai migrate() langsung, tanpa penjaga ini.
     */
    public function migrateIfNeeded(): void
    {
        if ($this->isUpToDate()) {
            return;
        }

        $this->migrate();
    }

    /**
     * Yang dibandingkan adalah nama berkas migrasi, bukan jumlahnya: NativePHP
     * menyumbang dua migrasi dari dalam paketnya sendiri, jadi catatan di database
     * selalu lebih banyak daripada isi database/migrations dan perbandingan angka
     * tidak pernah cocok — akibatnya migrasi jalan lagi di setiap layar yang dibuka.
     *
     * Setiap keraguan — tabelnya belum ada, kueri gagal — dijawab dengan menjalankan migrasi.
     */
    private function isUpToDate(): bool
    {
        try {
            $migrator = app('migrator');

            $shipped = $migrator->getMigrationFiles(
                array_merge($migrator->paths(), [database_path('migrations')])
            );

            return array_diff(array_keys($shipped), $migrator->getRepository()->getRan()) === [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Menjalankan Artisan dari dalam permintaan web memaksa Laravel mendaftarkan
     * seluruh perintah console, dan salah satunya — `completion` bawaan Symfony —
     * membaca $_SERVER['PHP_SELF'] saat dibuat. Runtime PHP yang ditanam NativePHP
     * ke dalam ponsel tidak menyediakannya, jadi migrasi selalu gagal di situ,
     * setelah tabelnya sebenarnya sudah terbentuk.
     *
     * Di web biasa nilai itu selalu ada, jadi baris ini hanya berlaku di ponsel.
     */
    private function makeArtisanRunnable(): void
    {
        $_SERVER['PHP_SELF'] ??= 'artisan';
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

        $this->reporter->reportThrowable(DiagnosticsReporter::MIGRATION_FAILURE, $exception);
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
