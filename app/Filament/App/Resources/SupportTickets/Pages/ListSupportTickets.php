<?php

namespace App\Filament\App\Resources\SupportTickets\Pages;

use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupportTickets extends ListRecords
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Kirim tiket baru'),
        ];
    }
}
