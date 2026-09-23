<?php

namespace App\Filament\App\Resources\SupportTickets;

use App\Filament\App\Resources\SupportTickets\Pages\CreateSupportTicket;
use App\Filament\App\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\App\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\App\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\App\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    /**
     * Tiket milik akun, bukan milik buku, jadi tidak ikut dibatasi tenant.
     */
    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'bantuan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Lifebuoy;

    protected static ?string $navigationLabel = 'Bantuan';

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'tiket bantuan';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 3;

    /**
     * Pengguna hanya boleh melihat tiketnya sendiri.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereBelongsTo(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'create' => CreateSupportTicket::route('/baru'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }
}
