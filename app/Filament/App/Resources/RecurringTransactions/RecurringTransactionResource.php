<?php

namespace App\Filament\App\Resources\RecurringTransactions;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Filament\App\Concerns\RequiresPremium;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\RecurringTransactions\Pages\ManageRecurringTransactions;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\RecurringTransaction;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RecurringTransactionResource extends Resource
{
    use RequiresPremium;

    protected static ?string $model = RecurringTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Transactions;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'transaksi berulang';

    protected static ?string $pluralModelLabel = 'transaksi berulang';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('type')
                    ->label('Jenis')
                    ->options(TransactionType::class)
                    ->default(TransactionType::Expense)
                    ->inline()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('category_id', null))
                    ->required(),
                Select::make('frequency')
                    ->label('Frekuensi')
                    ->options(RecurringFrequency::class)
                    ->default(RecurringFrequency::Monthly)
                    ->required(),
                Select::make('account_id')
                    ->label('Akun')
                    ->relationship('account', 'name')
                    ->preload()
                    ->required(),
                Select::make('category_id')
                    ->label('Kategori')
                    ->relationship(
                        'category',
                        'name',
                        fn (Builder $query, Get $get) => $query->where('type', $get('type')),
                    )
                    ->preload(),
                MoneyInput::make('amount')
                    ->label('Nominal')
                    ->minValue(1)
                    ->required(),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->placeholder('Gaji bulanan, tagihan listrik, ...')
                    ->rows(2)
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label('Mulai tanggal')
                    ->default(today())
                    ->required()
                    ->disabledOn('edit'),
                DatePicker::make('end_date')
                    ->label('Berakhir tanggal (opsional)')
                    ->afterOrEqual('start_date'),
                DatePicker::make('next_run_date')
                    ->label('Jadwal berikutnya')
                    ->required()
                    ->visibleOn('edit'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('next_run_date')
            ->columns([
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES),
                TextColumn::make('frequency')
                    ->label('Frekuensi')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('account.name')
                    ->label('Akun'),
                TextColumn::make('next_run_date')
                    ->label('Jadwal berikutnya')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->placeholder('Tanpa batas')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Transaksi yang sudah dibuat dari jadwal ini tetap tersimpan.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecurringTransactions::route('/'),
        ];
    }
}
