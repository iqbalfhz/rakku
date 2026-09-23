<?php

namespace App\Actions;

use App\Enums\SupportTicketStatus;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReplied;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ReplyToSupportTicket
{
    /**
     * Tambahkan balasan ke tiket, perbarui statusnya, lalu kabari pihak seberang.
     *
     * @param  array{body: string, attachment_path?: string|null}  $data
     */
    public function handle(SupportTicket $ticket, User $author, array $data): SupportMessage
    {
        $message = new SupportMessage([
            'body' => $data['body'],
            'attachment_path' => $data['attachment_path'] ?? null,
        ]);
        $message->author()->associate($author);

        $ticket->messages()->save($message);

        $ticket->forceFill([
            'status' => $author->is_admin ? SupportTicketStatus::Answered : SupportTicketStatus::Open,
            'last_message_at' => $message->created_at,
        ])->save();

        Notification::send($this->recipients($ticket, $author), new SupportTicketReplied($message));

        return $message;
    }

    /**
     * Balasan admin dikirim ke pemilik tiket, balasan pengguna dikirim ke semua admin.
     *
     * @return Collection<int, User>
     */
    private function recipients(SupportTicket $ticket, User $author): Collection
    {
        return $author->is_admin
            ? collect([$ticket->user])
            : User::query()->where('is_admin', true)->get()->toBase();
    }
}
