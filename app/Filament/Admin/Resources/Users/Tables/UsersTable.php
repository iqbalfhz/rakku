<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Resources\Users\Actions\ActivatePremiumAction;
use App\Filament\Admin\Resources\Users\Actions\DowngradeToFreeAction;
use App\Filament\Admin\Resources\Users\Actions\ToggleAdminAction;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('currentSubscription')->withCount('books'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('plan')
                    ->label('Plan')
                    ->state(fn (User $record): string => $record->isPremium() ? 'Premium' : 'Free')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Premium' ? 'warning' : 'gray'),
                TextColumn::make('currentSubscription.expires_at')
                    ->label('Premium sampai')
                    ->date('d M Y')
                    ->placeholder('-'),
                TextColumn::make('books_count')
                    ->label('Buku'),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('premium')
                    ->label('Plan')
                    ->placeholder('Semua plan')
                    ->trueLabel('Premium')
                    ->falseLabel('Free')
                    ->queries(
                        true: fn (Builder $query) => $query->premium(),
                        false: fn (Builder $query) => $query->whereNot(fn (Builder $users) => $users->premium()),
                    ),
                TernaryFilter::make('is_admin')
                    ->label('Admin')
                    ->placeholder('Semua pengguna')
                    ->trueLabel('Hanya admin')
                    ->falseLabel('Bukan admin'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ActivatePremiumAction::make(),
                    DowngradeToFreeAction::make(),
                    ToggleAdminAction::make(),
                ]),
            ]);
    }
}
