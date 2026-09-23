<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource as AdminTicketResource;
use App\Models\SupportMessage;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Kabari pihak seberang, admin atau pengguna, bahwa tiketnya dibalas.
 */
class SupportTicketReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportMessage $message) {}

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
            ->info()
            ->title($this->title())
            ->body(Str::limit($this->message->body, 120))
            ->actions([
                Action::make('open')
                    ->label('Buka tiket')
                    ->url($this->ticketUrl($notifiable))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Balasan tiket: {$this->message->ticket->subject}")
            ->greeting("Halo {$notifiable->name},")
            ->line($this->title().':')
            ->line($this->message->body)
            ->action('Buka tiket', $this->ticketUrl($notifiable));
    }

    private function title(): string
    {
        return $this->message->isFromAdmin()
            ? 'Admin membalas tiket Anda'
            : "{$this->message->author->name} membalas tiket";
    }

    /**
     * Admin dibawa ke panel admin, pengguna ke halaman bantuan di aplikasinya.
     */
    private function ticketUrl(object $notifiable): string
    {
        if ($notifiable->is_admin) {
            return AdminTicketResource::getUrl('view', ['record' => $this->message->ticket], panel: 'admin');
        }

        return url('/app');
    }
}
