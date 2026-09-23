<?php

namespace App\Filament\App\Resources\SupportTickets\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->emptyStateHeading('Belum ada tiket')
            ->emptyStateDescription('Kirim tiket kalau ada yang bermasalah atau ingin ditanyakan. Admin membalas di halaman ini juga.')
            ->columns([
                TextColumn::make('subject')
                    ->label('Judul')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('last_message_at')
                    ->label('Pesan terakhir')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ]);
    }
}
