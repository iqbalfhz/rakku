<?php

namespace App\Filament\App\Resources\Accounts;

use App\Enums\AccountType;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Accounts\Pages\ManageAccounts;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Account;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MasterData;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'akun';

    protected static ?string $pluralModelLabel = 'akun & dompet';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama akun')
                    ->placeholder('Cash, BCA, GoPay, ...')
                    ->required()
                    ->maxLength(100),
                Select::make('type')
                    ->label('Jenis')
                    ->options(AccountType::class)
                    ->default(AccountType::Cash)
                    ->required(),
                MoneyInput::make('initial_balance')
                    ->label('Saldo awal')
                    ->helperText('Saldo saat akun mulai dicatat. Mengubahnya akan menggeser saldo saat ini.')
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama akun')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('initial_balance')
                    ->label('Saldo awal')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('current_balance')
                    ->label('Saldo saat ini')
                    ->money('IDR')
                    ->color(fn (Account $record): string => (float) $record->current_balance < 0 ? 'danger' : 'success')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total saldo')->money('IDR')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(AccountType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Account $record): void {
                        if (! $record->hasActivity()) {
                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Akun tidak bisa dihapus')
                            ->body('Akun ini sudah punya riwayat transaksi, transfer, atau cicilan.')
                            ->send();

                        $action->cancel();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccounts::route('/'),
        ];
    }
}
