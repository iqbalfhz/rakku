<?php

namespace App\Filament\App\Resources\Transfers;

use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Transfers\Pages\ManageTransfers;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Transfer;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Transactions;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'transfer';

    protected static ?string $pluralModelLabel = 'transfer antar akun';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('from_account_id')
                    ->label('Dari akun')
                    ->relationship('fromAccount', 'name')
                    ->preload()
                    ->required(),
                Select::make('to_account_id')
                    ->label('Ke akun')
                    ->relationship('toAccount', 'name')
                    ->preload()
                    ->different('from_account_id')
                    ->validationMessages(['different' => 'Akun tujuan harus berbeda dari akun asal.'])
                    ->required(),
                MoneyInput::make('amount')
                    ->label('Nominal')
                    ->minValue(0.01)
                    ->required(),
                DatePicker::make('transfer_date')
                    ->label('Tanggal')
                    ->default(today())
                    ->required(),
                Textarea::make('description')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('transfer_date', 'desc')
            ->columns([
                TextColumn::make('transfer_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('fromAccount.name')
                    ->label('Dari akun'),
                TextColumn::make('toAccount.name')
                    ->label('Ke akun'),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransfers::route('/'),
        ];
    }
}
