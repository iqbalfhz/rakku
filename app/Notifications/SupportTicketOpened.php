<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabari admin lewat lonceng panel dan email saat ada tiket bantuan baru.
 */
class SupportTicketOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

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
            ->title('Tiket bantuan baru')
            ->body("{$this->ticket->ticket_number} · {$this->ticket->user->name}: {$this->ticket->subject}")
            ->actions([
                Action::make('open')
                    ->label('Buka tiket')
                    ->url($this->ticketUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tiket {$this->ticket->ticket_number}: {$this->ticket->subject}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$this->ticket->user->name} ({$this->ticket->user->email}) mengirim tiket bantuan.")
            ->line("Nomor tiket: {$this->ticket->ticket_number}")
            ->line("Judul: {$this->ticket->subject}")
            ->line($this->ticket->messages()->value('body') ?? '')
            ->action('Buka tiket', $this->ticketUrl());
    }

    private function ticketUrl(): string
    {
        return SupportTicketResource::getUrl('view', ['record' => $this->ticket], panel: 'admin');
    }
}
