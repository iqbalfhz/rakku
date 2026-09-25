<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari admin saat pencadangan harian tidak jadi.
 *
 * Perintahnya jalan dari scheduler, dan keluhannya selama ini hanya dicetak ke
 * keluaran cron yang tidak ada yang membacanya. Cadangan yang diam-diam berhenti
 * baru ketahuan saat cadangannya dibutuhkan — dan saat itu sudah terlambat.
 */
class BackupFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('RakKu: pencadangan gagal')
            ->greeting("Halo {$notifiable->name},")
            ->line('Pencadangan file unggahan hari ini tidak selesai:')
            ->line($this->reason)
            ->line('Selama ini belum diperbaiki, data yang ada hanya tersimpan di server itu sendiri.');
    }
}
