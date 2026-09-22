<?php

namespace App\Filament\App\Resources\Categories;

use App\Enums\TransactionType;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Tag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::BookSettings;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'kategori';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama kategori')
                    ->required()
                    ->maxLength(100),
                ToggleButtons::make('type')
                    ->label('Jenis')
                    ->options(TransactionType::class)
                    ->default(TransactionType::Expense)
                    ->inline()
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
                    ->label('Nama kategori')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('transactions_count')
                    ->label('Jumlah transaksi')
                    ->counts('transactions')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(TransactionType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Transaksi pada kategori ini akan menjadi "Tanpa Kategori" dan budget-nya ikut terhapus.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
