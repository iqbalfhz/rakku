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
 * Ingatkan pengguna sebelum premiumnya habis, supaya perpanjangan tidak terlewat.
 */
class PremiumExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $daysLeft) {}

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
            ->warning()
            ->title("Premium berakhir {$this->dueLabel()}")
            ->body(self::LOCKED_FEATURES)
            ->actions([
                Action::make('renew')
                    ->label('Perpanjang')
                    ->url(Subscription::urlFor($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Premium RakKu Anda berakhir {$this->dueLabel()}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Masa aktif premium Anda berakhir {$this->dueLabel()}.")
            ->line(self::LOCKED_FEATURES)
            ->action('Perpanjang sekarang', Subscription::urlFor($notifiable))
            ->line('Catatan keuangan Anda tetap tersimpan apa pun yang terjadi.');
    }

    private const string LOCKED_FEATURES = 'Setelah itu Invoice, Utang-Piutang, Laporan Laba-Rugi, transaksi berulang, dan buku tambahan terkunci sampai premium diperpanjang.';

    private function dueLabel(): string
    {
        return match ($this->daysLeft) {
            0 => 'hari ini',
            1 => 'besok',
            default => "{$this->daysLeft} hari lagi",
        };
    }
}
