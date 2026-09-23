<?php

namespace App\Actions;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketOpened;
use Illuminate\Support\Facades\Notification;

class OpenSupportTicket
{
    /**
     * Buat tiket beserta pesan pertamanya, lalu kabari semua admin.
     *
     * @param  array{subject: string, body: string, attachment_path?: string|null}  $data
     */
    public function handle(User $user, array $data): SupportTicket
    {
        $ticket = $user->supportTickets()->create(['subject' => $data['subject']]);

        $message = new SupportMessage([
            'body' => $data['body'],
            'attachment_path' => $data['attachment_path'] ?? null,
        ]);
        $message->author()->associate($user);

        $ticket->messages()->save($message);

        $ticket->forceFill(['last_message_at' => now()])->save();

        Notification::send(User::query()->where('is_admin', true)->get(), new SupportTicketOpened($ticket));

        return $ticket;
    }
}
