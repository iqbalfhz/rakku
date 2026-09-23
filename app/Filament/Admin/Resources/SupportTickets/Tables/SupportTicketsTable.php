<?php

namespace App\Filament\Admin\Resources\SupportTickets\Tables;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('messages'))
            ->defaultSort('last_message_at')
            ->columns([
                TextColumn::make('last_message_at')
                    ->label('Pesan terakhir')
                    ->since()
                    ->description(fn (SupportTicket $record): string => $record->last_message_at?->translatedFormat('j M Y H:i') ?? '-')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->description(fn (SupportTicket $record): string => $record->user->email)
                    ->searchable(),
                TextColumn::make('ticket_number')
                    ->label('Nomor')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Judul')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('messages_count')
                    ->label('Pesan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SupportTicketStatus::class)
                    ->default(SupportTicketStatus::Open->value),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ]);
    }
}
