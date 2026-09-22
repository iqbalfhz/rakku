<?php

namespace App\Filament\App\Resources\Debts;

use App\Filament\App\Concerns\RequiresPremium;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Debts\Pages\CreateDebt;
use App\Filament\App\Resources\Debts\Pages\EditDebt;
use App\Filament\App\Resources\Debts\Pages\ListDebts;
use App\Filament\App\Resources\Debts\RelationManagers\PaymentsRelationManager;
use App\Filament\App\Resources\Debts\Schemas\DebtForm;
use App\Filament\App\Resources\Debts\Tables\DebtsTable;
use App\Models\Debt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DebtResource extends Resource
{
    use RequiresPremium;

    protected static ?string $model = Debt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::DebtsAndInvoices;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'utang-piutang';

    protected static ?string $pluralModelLabel = 'utang-piutang';

    protected static ?string $recordTitleAttribute = 'counterparty_name';

    public static function form(Schema $schema): Schema
    {
        return DebtForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DebtsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebts::route('/'),
            'create' => CreateDebt::route('/create'),
            'edit' => EditDebt::route('/{record}/edit'),
        ];
    }
}
