<?php

namespace App\Filament\Admin\Resources\SupportTickets;

use App\Filament\Admin\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Admin\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Admin\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $slug = 'bantuan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Lifebuoy;

    protected static ?string $navigationLabel = 'Bantuan';

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'tiket bantuan';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    /**
     * Tampilkan jumlah tiket yang belum dijawab di menu admin.
     */
    public static function getNavigationBadge(): ?string
    {
        $openCount = SupportTicket::query()->open()->count();

        return $openCount > 0 ? (string) $openCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }
}
