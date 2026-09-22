<?php

namespace App\Filament\App\Resources\Debts\Schemas;

use App\Enums\DebtType;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DebtForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Jenis')
                            ->options(DebtType::class)
                            ->default(DebtType::Receivable)
                            ->helperText('Piutang: orang lain berutang ke Anda. Utang: Anda berutang ke orang lain.')
                            ->inline()
                            ->required(),
                        TextInput::make('counterparty_name')
                            ->label('Nama pihak terkait')
                            ->required()
                            ->maxLength(255),
                        MoneyInput::make('amount')
                            ->label('Jumlah awal')
                            ->minValue(0.01)
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Jatuh tempo'),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('reminder_enabled')
                            ->label('Kirim pengingat jatuh tempo')
                            ->helperText('Notifikasi dikirim H-3, H-1, dan pada hari jatuh tempo.')
                            ->default(true),
                    ]),
            ]);
    }
}
