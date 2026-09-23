<?php

namespace App\Notifications;

use App\Filament\App\Pages\Subscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Jelaskan kenapa menu premium menghilang, supaya pengguna tidak mengira aplikasinya rusak.
 */
class PremiumExpired extends Notification implements ShouldQueue
{
    use Queueable;

    private const string TITLE = 'Premium Anda sudah berakhir';

    private const string SUMMARY = 'Invoice, Utang-Piutang, Laporan Laba-Rugi, transaksi berulang, dan buku tambahan terkunci sampai premium diperpanjang. Semua catatan Anda tetap tersimpan dan akan terbuka lagi begitu diperpanjang.';

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Lonceng panel diisi seketika; hanya emailnya yang menunggu giliran di queue.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title(self::TITLE)
            ->body(self::SUMMARY)
            ->actions([
                Action::make('renew')
                    ->label('Aktifkan lagi')
                    ->url(Subscription::urlFor($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Premium RakKu Anda sudah berakhir')
            ->greeting("Halo {$notifiable->name},")
            ->line(self::SUMMARY)
            ->action('Aktifkan lagi', Subscription::urlFor($notifiable));
    }
}
