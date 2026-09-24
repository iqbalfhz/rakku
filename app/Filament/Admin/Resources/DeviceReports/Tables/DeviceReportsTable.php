<?php

namespace App\Filament\Admin\Resources\DeviceReports\Tables;

use App\Enums\DeviceReportKind;
use App\Models\DeviceReport;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeviceReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Kabar kerusakan yang dikirim aplikasi ponsel pengguna.')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->description(fn (DeviceReport $record): string => $record->user->email)
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Pesan')
                    ->wrap()
                    ->limit(160)
                    ->searchable(),
                TextColumn::make('context')
                    ->label('Perangkat')
                    ->state(fn (DeviceReport $record): string => self::deviceLine($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Jenis')
                    ->options(DeviceReportKind::class),
            ])
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    /**
     * Satu baris ringkas tentang perangkat pengirim, supaya pola kerusakan terlihat.
     */
    private static function deviceLine(DeviceReport $record): string
    {
        $context = $record->context ?? [];

        return collect([$context['platform'] ?? null, $context['app_version'] ?? null])
            ->filter()
            ->implode(' · ') ?: '—';
    }
}
