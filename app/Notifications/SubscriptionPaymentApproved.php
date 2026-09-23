<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari pengguna lewat lonceng aplikasi dan email bahwa premiumnya sudah aktif.
 */
class SubscriptionPaymentApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SubscriptionPayment $payment,
        public ?CarbonInterface $expiresAt,
    ) {}

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
            ->success()
            ->title('Premium aktif')
            ->body($this->summary())
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Premium RakKu Anda sudah aktif')
            ->greeting("Halo {$notifiable->name},")
            ->line($this->summary())
            ->action('Buka RakKu', url('/app'))
            ->line('Terima kasih sudah berlangganan.');
    }

    private function summary(): string
    {
        return $this->expiresAt === null
            ? 'Pembayaran Anda sudah diverifikasi. Premium Anda berlaku tanpa batas waktu.'
            : "Pembayaran Anda sudah diverifikasi. Premium berlaku sampai {$this->expiresAt->translatedFormat('j F Y')}.";
    }
}
