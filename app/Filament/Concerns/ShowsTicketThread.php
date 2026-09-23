<?php

namespace App\Filament\Concerns;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Tampilkan isi percakapan sebuah tiket sebagai rangkaian kartu, dipakai di panel app dan admin.
 */
trait ShowsTicketThread
{
    /**
     * Balasan pihak seberang muncul sendiri selama halaman tiket dibuka.
     */
    public const string THREAD_POLLING_INTERVAL = '15s';

    /**
     * Skema di-cache per request, jadi setelah membalas isinya perlu dibangun ulang
     * agar pesan baru langsung tampil tanpa memuat ulang halaman.
     */
    public function refreshTicketThread(): void
    {
        $this->getRecord()->unsetRelation('messages');

        $this->cachedSchemas = [];
    }

    /**
     * Lebar maksimal satu gelembung pesan supaya percakapan tetap enak dibaca di layar lebar.
     */
    private const string BUBBLE_WIDTH = '38rem';

    public function ticketThread(Schema $schema): Schema
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getRecord();

        return $schema->components([
            Group::make(
                $ticket->messages()->with('author')->get()
                    ->map(fn (SupportMessage $message): Section => $this->messageSection($message))
                    ->all(),
            )
                ->columnSpanFull()
                ->poll(self::THREAD_POLLING_INTERVAL),
        ]);
    }

    /**
     * Pesan admin didorong ke kanan dan pesan pengguna ke kiri, seperti percakapan pada umumnya.
     * Gaya ditulis inline karena CSS Filament yang sudah dikompilasi tidak memuat kelas utilitas ini.
     */
    private function messageSection(SupportMessage $message): Section
    {
        $isFromAdmin = $message->isFromAdmin();

        return Section::make($isFromAdmin ? "Admin — {$message->author->name}" : $message->author->name)
            ->description($message->created_at->translatedFormat('j F Y, H:i'))
            ->icon($isFromAdmin ? 'heroicon-o-lifebuoy' : 'heroicon-o-user')
            ->iconColor($isFromAdmin ? 'primary' : 'gray')
            ->extraAttributes([
                'style' => 'width: 100%; max-width: '.self::BUBBLE_WIDTH.'; '
                    .($isFromAdmin ? 'margin-left: auto;' : 'margin-right: auto;'),
            ])
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
