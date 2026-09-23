<?php

namespace App\Filament\Actions;

use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class CloseTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'closeTicket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tandai selesai')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->visible(fn (SupportTicket $record): bool => ! $record->isClosed())
            ->requiresConfirmation()
            ->modalDescription('Tiket yang sudah selesai tidak bisa dibalas lagi. Pengguna bisa membuka tiket baru kalau masih ada masalah.')
            ->action(function (SupportTicket $record): void {
                $record->close();

                $this->successNotificationTitle('Tiket ditandai selesai');
                $this->success();
            });
    }
}
