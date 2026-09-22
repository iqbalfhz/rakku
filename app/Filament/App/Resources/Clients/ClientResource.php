<?php

namespace App\Filament\App\Resources\Clients;

use App\Filament\App\Concerns\RequiresPremium;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Clients\Pages\ManageClients;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ClientResource extends Resource
{
    use RequiresPremium;

    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::DebtsAndInvoices;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'klien';

    protected static ?string $pluralModelLabel = 'klien';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Telepon')
                    ->tel()
                    ->maxLength(30),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('-'),
                TextColumn::make('invoices_count')
                    ->label('Invoice')
                    ->counts('invoices'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Client $record): void {
                        if ($record->invoices()->doesntExist()) {
                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Klien tidak bisa dihapus')
                            ->body('Hapus dulu invoice milik klien ini.')
                            ->send();

                        $action->cancel();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
        ];
    }
}
