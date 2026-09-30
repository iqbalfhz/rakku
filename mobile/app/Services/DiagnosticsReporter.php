<?php

namespace App\Services;

use App\Models\DeviceReport;
use Illuminate\Support\Collection;
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

    public function __construct(
        private ApiClient $apiClient,
        private TokenStore $tokenStore,
        private DeviceFacts $deviceFacts,
    ) {}

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
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return array_filter([
            'platform' => $this->deviceFacts->platform(),
            'device' => $this->deviceFacts->name(),
            'os' => $this->deviceFacts->operatingSystem(),
            'sdk' => $this->deviceFacts->sdkVersion(),
            'device_id' => $this->deviceFacts->id(),
            'is_virtual' => $this->deviceFacts->isVirtual(),
            'webview' => $this->deviceFacts->webViewVersion(),
            'language' => $this->deviceFacts->language(),
            // Versi yang dipasang NativePHP ke APK, bukan config bawaan Laravel yang
            // tidak pernah diisi — tanpa ini setiap laporan mengaku "dev".
            'app_version' => (string) config('nativephp.version', 'dev'),
            'php_version' => PHP_VERSION,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
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
