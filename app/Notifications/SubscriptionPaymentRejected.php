<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari pengguna lewat lonceng aplikasi dan email bahwa buktinya belum bisa diterima.
 */
class SubscriptionPaymentRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionPayment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title('Pembayaran belum bisa diverifikasi')
            ->body($this->payment->rejection_reason)
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pembayaran RakKu belum bisa diverifikasi')
            ->greeting("Halo {$notifiable->name},")
            ->line('Bukti transfer yang Anda kirim belum bisa kami terima dengan alasan berikut.')
            ->line($this->payment->rejection_reason)
            ->action('Kirim ulang bukti transfer', url('/app'))
            ->line('Silakan kirim ulang setelah diperbaiki, atau balas email ini kalau ada yang perlu ditanyakan.');
    }
}
