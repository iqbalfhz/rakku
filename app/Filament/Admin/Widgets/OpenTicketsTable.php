<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tiket bantuan yang belum dijawab, diurutkan dari yang paling lama menunggu.
 */
class OpenTicketsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table)
            ->heading('Tiket bantuan belum dijawab')
            ->query(fn (): Builder => SupportTicket::query()->open()->with('user')->withCount('messages'))
            ->defaultSort('last_message_at')
            ->filters([])
            ->emptyStateHeading('Tidak ada tiket yang menunggu jawaban');
    }
}
