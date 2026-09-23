<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\SubscriptionPayments\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Support\Rupiah;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari admin lewat lonceng panel dan email saat ada bukti transfer baru.
 */
class SubscriptionPaymentSubmitted extends Notification implements ShouldQueue
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
            ->warning()
            ->title('Pengajuan premium baru')
            ->body($this->summary())
            ->actions([
                Action::make('review')
                    ->label('Tinjau pembayaran')
                    ->url($this->reviewUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pengajuan premium dari {$this->payment->user->name}")
            ->greeting("Halo {$notifiable->name},")
            ->line($this->summary())
            ->line("Email pengguna: {$this->payment->user->email}")
            ->action('Tinjau pembayaran', $this->reviewUrl())
            ->line('Premium baru aktif setelah Anda menyetujuinya di panel admin.');
    }

    private function summary(): string
    {
        return sprintf(
            '%s mengirim bukti transfer paket %d bulan sebesar %s.',
            $this->payment->user->name,
            $this->payment->package->months(),
            Rupiah::format((float) $this->payment->amount),
        );
    }

    private function reviewUrl(): string
    {
        return SubscriptionPaymentResource::getUrl('index', panel: 'admin');
    }
}
