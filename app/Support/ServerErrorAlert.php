<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ServerErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Memberi tahu admin saat server bermasalah.
 *
 * Tanpa ini, satu-satunya jejak error server adalah laravel.log yang tidak ada yang
 * membacanya sampai ada pengguna mengeluh. Lonceng tidak dipakai di sini — error
 * server bukan hal yang ditindaklanjuti dari dalam panel, dan email lebih mungkin
 * terbaca saat panelnya sendiri sedang bermasalah.
 *
 * Yang di sini tidak pernah boleh melempar: kalau pelapor error ikut error, yang
 * hilang justru pesan aslinya.
 */
final class ServerErrorAlert
{
    /**
     * Error yang sama tidak dikabarkan lagi dalam kurun ini, supaya satu halaman
     * yang rusak tidak mengirim ratusan email.
     */
    public const int QUIET_MINUTES = 60;

    public static function send(Throwable $exception): void
    {
        try {
            if (! self::isFirstInAWhile($exception)) {
                return;
            }

            $admins = User::query()->where('is_admin', true)->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, new ServerErrorOccurred(
                $exception::class.': '.$exception->getMessage(),
                basename($exception->getFile()).':'.$exception->getLine(),
            ));
        } catch (Throwable) {
            // Tidak ada tempat mengadu soal kegagalan mengadu.
        }
    }

    private static function isFirstInAWhile(Throwable $exception): bool
    {
        $fingerprint = sha1($exception::class.'|'.$exception->getFile().'|'.$exception->getLine());

        return Cache::add("server-error:{$fingerprint}", true, now()->addMinutes(self::QUIET_MINUTES));
    }
}
