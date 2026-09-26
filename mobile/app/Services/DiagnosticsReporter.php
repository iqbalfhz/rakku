<?php

namespace App\Services;

use App\Models\DeviceReport;
use Illuminate\Support\Collection;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\System;
use Throwable;

/**
 * Mengabarkan kerusakan aplikasi ke server.
 *
 * Aplikasi yang rusak di ponsel orang lain tidak meninggalkan jejak apa pun: mereka
 * diam, lalu berhenti memakainya. Laporan ditulis dulu ke ponsel karena kerusakan
 * paling sering terjadi justru saat sinyal buruk, lalu dikirim saat ada kesempatan.
 *
 * Yang di sini tidak pernah boleh melempar: pelapor kerusakan yang ikut rusak
 * hanya menambah kerusakan.
 */
class DiagnosticsReporter
{
    public const string MIGRATION_FAILURE = 'migration_failure';

    public const string CRASH = 'crash';

    /**
     * Kerusakan yang sama tidak dilaporkan lagi dalam kurun ini, supaya satu layar
     * yang error berulang kali tidak membanjiri server dengan kabar yang sama.
     */
    public const int DUPLICATE_WINDOW_HOURS = 24;

    /**
     * Batas antrean. Ponsel yang rusak parah tidak boleh menghabiskan penyimpanannya
     * sendiri hanya untuk mengeluh.
     */
    public const int QUEUE_LIMIT = 50;

    /**
     * Jejak tumpukan bisa sangat panjang. Yang menjelaskan penyebab hampir selalu
     * ada di bagian awalnya, jadi sisanya dipotong daripada membebani penyimpanan
     * ponsel dan setiap pengiriman.
     */
    public const int DETAIL_LIMIT = 8000;

    /**
     * Satu panggilan jembatan per permintaan sudah cukup: isinya tidak berubah
     * di tengah jalan, dan tiap panggilan menyeberang ke sisi Kotlin.
     *
     * @var array<string, mixed>|null
     */
    private ?array $deviceInfo = null;

    public function __construct(private ApiClient $apiClient, private TokenStore $tokenStore) {}

    public function report(string $kind, string $message, ?string $detail = null): void
    {
        try {
            $fingerprint = $this->fingerprintOf($kind, $message);

            if ($this->reportedRecently($fingerprint) || $this->queueIsFull()) {
                return;
            }

            DeviceReport::query()->create([
                'kind' => $kind,
                'message' => mb_substr($message, 0, 2000),
                'detail' => $detail === null ? null : mb_substr($detail, 0, self::DETAIL_LIMIT),
                // Direkam sekarang, bukan saat dikirim: laporan menunggu sinyal, dan
                // sementara menunggu, aplikasinya bisa keburu diperbarui.
                'context' => $this->context(),
                'fingerprint' => $fingerprint,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // Tidak ada tempat mengadu soal kegagalan mengadu.
        }
    }

    /**
     * Melaporkan sebuah exception beserta tempat kejadiannya.
     *
     * Pesan exception saja memberi tahu apa yang pecah, bukan di mana. "Undefined
     * array key PHP_SELF" bisa datang dari berkas mana pun, dan menelusurinya di
     * ponsel orang lain tanpa berkas dan baris hampir mustahil.
     */
    public function reportThrowable(string $kind, Throwable $exception): void
    {
        $message = trim($exception->getMessage());

        // Sebagian exception tidak membawa kalimat apa pun. Nama kelasnya jauh lebih
        // berguna daripada baris kosong di daftar laporan.
        $this->report(
            $kind,
            $message === '' ? $exception::class : $message,
            $this->detailOf($exception),
        );
    }

    private function detailOf(Throwable $exception): string
    {
        return implode("\n", [
            $exception::class.': '.$exception->getMessage(),
            '',
            'Di '.$exception->getFile().' baris '.$exception->getLine(),
            '',
            $exception->getTraceAsString(),
        ]);
    }

    /**
     * Kirim yang menumpuk. Dipanggil dari sinkronisasi supaya ikut jadwal yang sama.
     */
    public function flush(): void
    {
        if (! $this->tokenStore->isSignedIn()) {
            return;
        }

        $pending = DeviceReport::query()->oldest('occurred_at')->limit(20)->get();

        if ($pending->isEmpty()) {
            return;
        }

        try {
            $this->apiClient->sendDeviceReports($this->payloadFor($pending));
        } catch (Throwable) {
            return;
        }

        DeviceReport::query()->whereIn('id', $pending->modelKeys())->delete();
    }

    public function pendingCount(): int
    {
        return DeviceReport::query()->count();
    }

    /**
     * @param  Collection<int, DeviceReport>  $reports
     * @return list<array<string, mixed>>
     */
    private function payloadFor(Collection $reports): array
    {
        return $reports->map(fn (DeviceReport $report): array => [
            'kind' => $report->kind,
            'message' => $report->message,
            'detail' => $report->detail,
            // Laporan yang mengantre sebelum kolom ini ada tidak punya catatan
            // keadaannya sendiri; keadaan sekarang masih lebih baik daripada kosong.
            'context' => $report->context ?? $this->context(),
            'occurred_at' => $report->occurred_at->utc()->toIso8601ZuluString(),
        ])->all();
    }

    /**
     * Keterangan tentang ponsel pengirim.
     *
     * Kerusakan jarang mengenai semua orang sekaligus: biasanya satu merek, satu
     * versi Android, atau satu versi aplikasi. Tanpa keterangan ini, pola seperti
     * itu tidak pernah terlihat dan setiap laporan berdiri sendiri tanpa petunjuk.
     *
     * Tidak ada alamat MAC di sini, dan itu disengaja. Sejak Android 6 semua
     * aplikasi hanya menerima 02:00:00:00:00:00, dan Google menggolongkannya
     * sebagai pengenal permanen yang tidak bisa direset — memintanya adalah alasan
     * penolakan di Play Store. Penggantinya `device_id`: pengenal per-aplikasi yang
     * ikut terhapus saat ponsel direset pabrik, yang justru itulah yang dibutuhkan
     * untuk membedakan perangkat.
     *
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $info = $this->deviceInfo();

        return array_filter([
            'platform' => $this->platform(),
            'device' => $this->deviceName($info),
            'os' => $this->operatingSystem($info),
            'sdk' => isset($info['androidSDKVersion']) ? (string) $info['androidSDKVersion'] : null,
            'device_id' => $this->deviceId(),
            'is_virtual' => $info['isVirtual'] ?? null,
            'webview' => $info['webViewVersion'] ?? null,
            'language' => $info['language'] ?? null,
            // Versi yang dipasang NativePHP ke APK, bukan config bawaan Laravel yang
            // tidak pernah diisi — tanpa ini setiap laporan mengaku "dev".
            'app_version' => (string) config('nativephp.version', 'dev'),
            'php_version' => PHP_VERSION,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function platform(): string
    {
        try {
            return System::isAndroid() ? 'Android' : (System::isIos() ? 'iOS' : 'Lainnya');
        } catch (Throwable) {
            return 'Lainnya';
        }
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function deviceName(array $info): ?string
    {
        // Build.MANUFACTURER datang huruf kecil semua, mis. "samsung SM-A536E".
        $parts = array_filter([
            isset($info['manufacturer']) ? ucfirst((string) $info['manufacturer']) : null,
            $info['model'] ?? null,
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function operatingSystem(array $info): ?string
    {
        $name = trim(($info['operatingSystem'] ?? '').' '.($info['osVersion'] ?? ''));

        return $name === '' ? null : $name;
    }

    private function deviceId(): ?string
    {
        try {
            $id = Device::getId();
        } catch (Throwable) {
            return null;
        }

        // Jembatannya menjawab "unknown" saat pengenalnya tidak terbaca.
        return $id === null || $id === 'unknown' ? null : $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceInfo(): array
    {
        if ($this->deviceInfo !== null) {
            return $this->deviceInfo;
        }

        try {
            $decoded = json_decode((string) Device::getInfo(), associative: true);
        } catch (Throwable) {
            $decoded = null;
        }

        return $this->deviceInfo = is_array($decoded) ? $decoded : [];
    }

    private function fingerprintOf(string $kind, string $message): string
    {
        return substr(sha1($kind.'|'.$message), 0, 40);
    }

    private function reportedRecently(string $fingerprint): bool
    {
        return DeviceReport::query()
            ->where('fingerprint', $fingerprint)
            ->where('occurred_at', '>=', now()->subHours(self::DUPLICATE_WINDOW_HOURS))
            ->exists();
    }

    private function queueIsFull(): bool
    {
        return DeviceReport::query()->count() >= self::QUEUE_LIMIT;
    }
}
