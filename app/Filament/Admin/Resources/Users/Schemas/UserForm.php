<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255),
                        TextEntry::make('currentSubscription.plan')
                            ->label('Plan saat ini')
                            ->badge(),
                        TextEntry::make('currentSubscription.expires_at')
                            ->label('Berlaku sampai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Tanpa batas'),
                    ]),
            ]);
    }
}
