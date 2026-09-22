<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Riwayat langganan';

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On('refreshRelationManager')]
    public function refreshSubscriptions(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('plan')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('expires_at')
                    ->label('Berakhir')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Tanpa batas'),
            ]);
    }
}
