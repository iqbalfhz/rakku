<?php

namespace App\Filament\App\Resources\SupportTickets\Pages;

use App\Actions\OpenSupportTicket;
use App\Filament\App\Resources\SupportTickets\SupportTicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupportTicket extends CreateRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected static ?string $title = 'Kirim tiket bantuan';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(OpenSupportTicket::class)->handle(auth()->user(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Tiket terkirim, admin akan membalas di sini';
    }
}
