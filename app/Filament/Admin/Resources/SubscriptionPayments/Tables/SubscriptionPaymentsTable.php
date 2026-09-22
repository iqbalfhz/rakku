<?php

namespace App\Filament\Admin\Resources\SubscriptionPayments\Tables;

use App\Enums\SubscriptionPaymentStatus;
use App\Filament\Admin\Resources\SubscriptionPayments\Actions\ApprovePaymentAction;
use App\Filament\Admin\Resources\SubscriptionPayments\Actions\RejectPaymentAction;
use App\Models\SubscriptionPayment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class SubscriptionPaymentsTable
{
    /**
     * Berapa lama link bukti transfer bisa dibuka setelah admin mengkliknya.
     */
    private const int PROOF_LINK_MINUTES = 5;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->description(fn (SubscriptionPayment $record): string => $record->user->email)
                    ->searchable(),
                TextColumn::make('package')
                    ->label('Paket')
                    ->state(fn (SubscriptionPayment $record): string => "{$record->package->months()} bulan"),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', 0),
                ImageColumn::make('proof_path')
                    ->label('Bukti')
                    ->disk(SubscriptionPayment::proofDisk())
                    ->visibility('private')
                    ->square(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SubscriptionPaymentStatus::class)
                    ->default(SubscriptionPaymentStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('openProof')
                    ->label('Lihat bukti')
                    ->icon(Heroicon::OutlinedPhoto)
                    ->color('gray')
                    ->url(fn (SubscriptionPayment $record): string => Storage::disk(SubscriptionPayment::proofDisk())
                        ->temporaryUrl($record->proof_path, now()->addMinutes(self::PROOF_LINK_MINUTES)))
                    ->openUrlInNewTab(),
                ApprovePaymentAction::make(),
                RejectPaymentAction::make(),
            ]);
    }
}
