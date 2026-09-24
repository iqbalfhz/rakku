<?php

namespace App\Services;

use App\Models\DeviceReport;
use Illuminate\Support\Collection;
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

    public function __construct(private ApiClient $apiClient, private TokenStore $tokenStore) {}

    public function report(string $kind, string $message): void
    {
        try {
            $fingerprint = $this->fingerprintOf($kind, $message);

            if ($this->reportedRecently($fingerprint) || $this->queueIsFull()) {
                return;
            }

            DeviceReport::query()->create([
                'kind' => $kind,
                'message' => mb_substr($message, 0, 2000),
                'fingerprint' => $fingerprint,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // Tidak ada tempat mengadu soal kegagalan mengadu.
        }
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
        $context = $this->context();

        return $reports->map(fn (DeviceReport $report): array => [
            'kind' => $report->kind,
            'message' => $report->message,
            'context' => $context,
            'occurred_at' => $report->occurred_at->utc()->toIso8601ZuluString(),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function context(): array
    {
        try {
            $platform = System::isAndroid() ? 'Android' : (System::isIos() ? 'iOS' : 'Lainnya');
        } catch (Throwable) {
            $platform = 'Lainnya';
        }

        return [
            'platform' => $platform,
            // Versi yang dipasang NativePHP ke APK, bukan config bawaan Laravel yang
            // tidak pernah diisi — tanpa ini setiap laporan mengaku "dev".
            'app_version' => (string) config('nativephp.version', 'dev'),
            'php_version' => PHP_VERSION,
        ];
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
