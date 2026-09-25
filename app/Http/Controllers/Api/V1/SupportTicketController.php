<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OpenSupportTicket;
use App\Actions\ReplyToSupportTicket;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mengadu dari dalam aplikasi ponsel.
 *
 * Orang yang aplikasinya bermasalah justru saat itulah paling butuh mengadu, dan
 * menyuruh mereka pindah ke browser adalah cara kehilangan mereka.
 */
class SupportTicketController extends Controller
{
    /**
     * Berapa banyak tiket lama yang ikut dikirim ke ponsel.
     */
    private const int HISTORY_LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->supportTickets()
            ->latest('last_message_at')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        return response()->json(['tickets' => $tickets->map($this->ticketPayload(...))->all()]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        $ticket->load(['messages.author']);

        return response()->json([
            'ticket' => $this->ticketPayload($ticket),
            'messages' => $ticket->messages->map($this->messagePayload(...))->all(),
        ]);
    }

    public function store(Request $request, OpenSupportTicket $openSupportTicket): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        $ticket = $openSupportTicket->handle($request->user(), [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'attachment_path' => $this->storeAttachment($request),
        ]);

        return response()->json(['ticket' => $this->ticketPayload($ticket)], 201);
    }

    public function reply(Request $request, SupportTicket $ticket, ReplyToSupportTicket $replyToSupportTicket): JsonResponse
    {
        $this->authorizeTicket($request, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'image', 'max:5120'],
        ]);

        $message = $replyToSupportTicket->handle($ticket, $request->user(), [
            'body' => $data['body'],
            'attachment_path' => $this->storeAttachment($request),
        ]);

        return response()->json(['message' => $this->messagePayload($message)], 201);
    }

    private function storeAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        return $request->file('attachment')->store(
            SupportMessage::ATTACHMENT_DIRECTORY,
            ['disk' => SupportMessage::attachmentDisk(), 'visibility' => 'private'],
        );
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->getLabel(),
            'last_message_at' => $ticket->last_message_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(SupportMessage $message): array
    {
        return [
            'body' => $message->body,
            'from_admin' => $message->isFromAdmin(),
            'author_name' => $message->author?->name,
            'has_attachment' => $message->attachment_path !== null,
            'sent_at' => $message->created_at->utc()->toIso8601ZuluString(),
        ];
    }
}
