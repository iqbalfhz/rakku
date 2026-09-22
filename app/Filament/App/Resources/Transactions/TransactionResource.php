<?php

namespace App\Filament\App\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Transactions\Pages\ManageTransactions;
use App\Filament\Exports\TransactionExporter;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Transaction;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Transactions;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'transaksi';

    protected static ?string $pluralModelLabel = 'transaksi';

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
                DatePicker::make('transaction_date')
                    ->label('Tanggal')
                    ->default(today())
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
                    ->rows(2)
                    ->columnSpanFull(),
                FileUpload::make('receipt_photo_path')
                    ->label('Foto struk (opsional)')
                    ->image()
                    ->disk(Transaction::RECEIPT_DISK)
                    ->directory(Transaction::RECEIPT_DIRECTORY)
                    ->visibility('private')
                    ->maxSize(5120)
                    ->openable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['debtPayment:id,transaction_id', 'invoice:id,transaction_id']))
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('account.name')
                    ->label('Akun'),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->placeholder('Tanpa Kategori'),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->color(fn (Transaction $record): string => $record->type->getColor())
                    ->sortable(),
                IconColumn::make('recurring_transaction_id')
                    ->label('Berulang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('receipt_photo_path')
                    ->label('Struk')
                    ->disk(Transaction::RECEIPT_DISK)
                    ->visibility('private')
                    ->square(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(TransactionType::class),
                SelectFilter::make('account')
                    ->label('Akun')
                    ->relationship('account', 'name')
                    ->preload(),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->preload(),
                Filter::make('transaction_date')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
                        ->when($data['until'], fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export')
                    ->exporter(TransactionExporter::class)
                    ->formats(fn (): array => auth()->user()->isPremium()
                        ? [ExportFormat::Csv, ExportFormat::Xlsx]
                        : [ExportFormat::Csv])
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereBelongsTo(Filament::getTenant())),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Transaction $record): bool => $record->isLocked()),
                DeleteAction::make()
                    ->hidden(fn (Transaction $record): bool => $record->isLocked()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransactions::route('/'),
        ];
    }
}
