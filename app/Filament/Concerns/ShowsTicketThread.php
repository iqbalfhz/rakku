<?php

namespace App\Filament\Concerns;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Tampilkan isi percakapan sebuah tiket sebagai rangkaian kartu, dipakai di panel app dan admin.
 */
trait ShowsTicketThread
{
    /**
     * Skema di-cache per request, jadi setelah membalas isinya perlu dibangun ulang
     * agar pesan baru langsung tampil tanpa memuat ulang halaman.
     */
    public function refreshTicketThread(): void
    {
        $this->getRecord()->unsetRelation('messages');

        $this->cachedSchemas = [];
    }

    public function ticketThread(Schema $schema): Schema
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getRecord();

        return $schema->components(
            $ticket->messages()->with('author')->get()
                ->map(fn (SupportMessage $message): Section => $this->messageSection($message))
                ->all(),
        );
    }

    private function messageSection(SupportMessage $message): Section
    {
        return Section::make($message->isFromAdmin() ? "Admin — {$message->author->name}" : $message->author->name)
            ->description($message->created_at->translatedFormat('j F Y, H:i'))
            ->icon($message->isFromAdmin() ? 'heroicon-o-lifebuoy' : 'heroicon-o-user')
            ->iconColor($message->isFromAdmin() ? 'primary' : 'gray')
            ->schema(array_filter([
                TextEntry::make("message-{$message->id}")
                    ->hiddenLabel()
                    ->state($message->body)
                    ->prose(),
                $message->attachment_path === null ? null : ImageEntry::make("attachment-{$message->id}")
                    ->hiddenLabel()
                    ->state($message->attachment_path)
                    ->disk(SupportMessage::attachmentDisk())
                    ->visibility('private')
                    ->imageHeight(200),
            ]));
    }
}
