<?php

namespace App\Filament\Admin\Resources\SupportTickets\Pages;

use App\Filament\Actions\CloseTicketAction;
use App\Filament\Actions\ReplyToTicketAction;
use App\Filament\Admin\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Concerns\ShowsTicketThread;
use App\Models\SupportTicket;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSupportTicket extends ViewRecord
{
    use ShowsTicketThread;

    protected static string $resource = SupportTicketResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->subject;
    }

    public function getSubheading(): ?string
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->getRecord();

        return "{$ticket->ticket_number} · {$ticket->user->name} · {$ticket->user->email} · {$ticket->status->getLabel()}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $this->ticketThread($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            ReplyToTicketAction::make(),
            CloseTicketAction::make(),
        ];
    }
}
