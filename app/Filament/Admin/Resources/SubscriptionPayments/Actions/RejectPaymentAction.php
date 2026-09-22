<?php

namespace App\Filament\Admin\Resources\SubscriptionPayments\Actions;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class RejectPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rejectPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (SubscriptionPayment $record): bool => $record->isPending())
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('Alasan penolakan')
                    ->helperText('Alasan ini ditampilkan ke pengguna agar mereka tahu apa yang harus diperbaiki.')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (SubscriptionPayment $record, array $data): void {
                $record->forceFill([
                    'status' => SubscriptionPaymentStatus::Rejected,
                    'rejection_reason' => $data['rejection_reason'],
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save();

                Notification::make()
                    ->danger()
                    ->title('Pembayaran belum bisa diverifikasi')
                    ->body($data['rejection_reason'])
                    ->sendToDatabase($record->user);

                $this->successNotificationTitle('Pengajuan ditolak');
                $this->success();
            });
    }
}
