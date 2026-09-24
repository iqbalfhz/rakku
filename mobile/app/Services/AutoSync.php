<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Native\Mobile\Facades\Network;
use Throwable;

/**
 * Sinkronisasi yang berjalan sendiri, tanpa pengguna menekan apa pun.
 *
 * Aturannya satu kalimat: yang belum terkirim tidak pernah ditunda. Catatan yang
 * masih di ponsel adalah satu-satunya yang benar-benar bisa hilang, jadi begitu
 * ada kesempatan ia langsung berangkat. Yang boleh ditunda hanyalah dua hal yang
 * tidak mendesak — mengecek kabar baru saat tidak ada apa-apa untuk dikirim, dan
 * mencoba lagi setelah gagal, supaya server yang sedang bermasalah tidak digedor.
 *
 * Yang di sini sengaja pendiam: gagal berarti diam dan dicoba lagi nanti, karena
 * pengguna tidak sedang meminta apa-apa dan tidak perlu diganggu peringatan.
 */
class AutoSync
{
    /**
     * Jeda untuk yang tidak mendesak saja: pengecekan kabar dan percobaan ulang setelah gagal.
     */
    public const int QUIET_PERIOD_SECONDS = 60;

    public function __construct(
        private SyncEngine $syncEngine,
        private TokenStore $tokenStore,
        private PendingChanges $pendingChanges,
    ) {}

    /**
     * Mengembalikan true hanya kalau sinkron benar-benar berjalan sampai selesai.
     */
    public function attempt(bool $force = false): bool
    {
        if (! $this->tokenStore->isSignedIn()) {
            return false;
        }

        if (! $force && ! $this->isDue()) {
            return false;
        }

        if ($this->isKnownOffline()) {
            return false;
        }

        // Ditandai gagal lebih dulu: percobaan yang menggantung pun ikut kena jeda.
        $this->tokenStore->rememberSyncAttempt(succeeded: false);

        try {
            $this->syncEngine->sync();
        } catch (Throwable) {
            return false;
        }

        $this->tokenStore->rememberSyncAttempt(succeeded: true);

        return true;
    }

    private function isDue(): bool
    {
        if (! $this->withinQuietPeriod()) {
            return true;
        }

        // Masih dalam jeda, tapi ada yang menunggu dan percobaan terakhir mulus:
        // tidak ada alasan menahannya.
        return $this->pendingChanges->exist() && ! $this->tokenStore->lastSyncAttemptFailed();
    }

    private function withinQuietPeriod(): bool
    {
        $lastAttempt = $this->tokenStore->lastSyncAttemptAt();

        if ($lastAttempt === null) {
            return false;
        }

        return CarbonImmutable::parse($lastAttempt)->diffInSeconds(now()) < self::QUIET_PERIOD_SECONDS;
    }

    /**
     * Kalau ponsel jelas-jelas tanpa sinyal, lebih baik menyerah sekarang daripada
     * menunggu dua puluh detik sampai permintaannya putus sendiri.
     */
    private function isKnownOffline(): bool
    {
        return Network::status()?->connected === false;
    }
}
