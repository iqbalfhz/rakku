<?php

namespace App\Filament\App\Resources\Budgets;

use App\Enums\TransactionType;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Budgets\Pages\ManageBudgets;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Budget;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChartPie;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::ReportsAndBudgets;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'budget';

    protected static ?string $pluralModelLabel = 'budget bulanan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Kategori pengeluaran')
                    ->relationship(
                        'category',
                        'name',
                        fn (Builder $query) => $query->ofType(TransactionType::Expense),
                    )
                    ->preload()
                    ->scopedUnique()
                    ->validationMessages(['unique' => 'Kategori ini sudah punya budget.'])
                    ->required(),
                MoneyInput::make('amount')
                    ->label('Limit per bulan')
                    ->minValue(1)
                    ->required(),
                Toggle::make('alert_enabled')
                    ->label('Kirim notifikasi saat mendekati/melewati limit')
                    ->live()
                    ->visible(fn (): bool => auth()->user()->isPremium()),
                TextInput::make('alert_threshold_percent')
                    ->label('Ambang peringatan')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->suffix('%')
                    ->default(80)
                    ->required()
                    ->visible(fn (Get $get): bool => auth()->user()->isPremium() && $get('alert_enabled')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withSpentIn(today()))
            ->description('Pemakaian dihitung dari pengeluaran bulan berjalan.')
            ->columns([
                TextColumn::make('category.name')
                    ->label('Kategori'),
                TextColumn::make('amount')
                    ->label('Limit')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->sortable(),
                TextColumn::make('spent')
                    ->label('Terpakai')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->default(0),
                TextColumn::make('usage')
                    ->label('Pemakaian')
                    ->state(fn (Budget $record): float => $record->usagePercent((float) $record->spent))
                    ->suffix('%')
                    ->badge()
                    ->color(fn (Budget $record, float $state): string => match (true) {
                        $state >= 100 => 'danger',
                        $state >= ($record->alert_threshold_percent ?? 80) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('remaining')
                    ->label('Sisa')
                    ->state(fn (Budget $record): float => (float) $record->amount - (float) $record->spent)
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->color(fn (float $state): ?string => $state < 0 ? 'danger' : null),
                IconColumn::make('alert_enabled')
                    ->label('Notifikasi')
                    ->boolean()
                    ->visible(fn (): bool => auth()->user()->isPremium()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBudgets::route('/'),
        ];
    }
}
