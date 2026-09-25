<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari admin saat server melempar error yang tidak terduga.
 *
 * Bukan pengganti log — isi lengkapnya tetap di laravel.log. Ini hanya supaya ada
 * yang tahu bahwa log itu perlu dibaca, karena tidak ada yang membacanya tiap hari.
 */
class ServerErrorOccurred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $summary,
        public string $location,
    ) {}

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
            ->subject('RakKu: error di server')
            ->greeting("Halo {$notifiable->name},")
            ->line('Server melempar error yang tidak terduga:')
            ->line($this->summary)
            ->line("Lokasi: {$this->location}")
            ->line('Rinciannya ada di storage/logs/laravel.log. Kabar yang sama tidak dikirim lagi dalam satu jam ke depan.');
    }
}
