<?php

namespace App\Filament\App\Resources\Invoices\Schemas;

use App\Filament\Forms\Components\MoneyInput;
use App\Models\Invoice;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled(fn (?Invoice $record): bool => $record?->isPaid() ?? false)
            ->components([
                Section::make('Detail invoice')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('client_id')
                            ->label('Klien')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                                TextInput::make('email')->label('Email')->email(),
                                TextInput::make('phone')->label('Telepon')->tel(),
                            ])
                            ->required(),
                        TextInput::make('invoice_number')
                            ->label('Nomor invoice')
                            ->default(fn (): string => Invoice::nextNumberFor(Filament::getTenant()))
                            ->scopedUnique()
                            ->required()
                            ->maxLength(50),
                        DatePicker::make('issue_date')
                            ->label('Tanggal terbit')
                            ->default(today())
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Jatuh tempo')
                            ->default(today()->addDays(14))
                            ->afterOrEqual('issue_date')
                            ->required(),
                    ]),
                Section::make('Item')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->columns(6)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah item')
                            ->live(onBlur: true)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Deskripsi')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->required(),
                                MoneyInput::make('unit_price')
                                    ->label('Harga satuan')
                                    ->required()
                                    ->columnSpan(2),
                            ]),
                        TextEntry::make('total')
                            ->label('Total')
                            ->state(fn (Get $get): string => Number::currency(self::calculateTotal($get('items') ?? []), 'IDR'))
                            ->weight('bold'),
                    ]),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->placeholder('Info rekening pembayaran, syarat, dll.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<array-key, array{quantity?: mixed, unit_price?: mixed}>  $items
     */
    private static function calculateTotal(array $items): float
    {
        return collect($items)->sum(fn (array $item): float => (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0));
    }
}
