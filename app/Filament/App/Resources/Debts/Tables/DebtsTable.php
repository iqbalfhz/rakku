<?php

namespace App\Filament\App\Resources\Debts\Tables;

use App\Enums\DebtStatus;
use App\Enums\DebtType;
use App\Models\Debt;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DebtsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('counterparty_name')
                    ->label('Pihak terkait')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Jumlah awal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('remaining_amount')
                    ->label('Sisa')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh tempo')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->color(fn (Debt $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Debt $record): ?string => $record->isOverdue() ? 'Lewat jatuh tempo' : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                IconColumn::make('reminder_enabled')
                    ->label('Pengingat')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(DebtType::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DebtStatus::class)
                    ->default(DebtStatus::Unpaid->value),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Debt $record): void {
                        if ($record->payments()->doesntExist()) {
                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Utang-piutang tidak bisa dihapus')
                            ->body('Hapus dulu riwayat cicilannya agar saldo akun tetap sesuai.')
                            ->send();

                        $action->cancel();
                    }),
            ]);
    }
}
