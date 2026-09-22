<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;

class ActivatePremiumAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'activatePremium';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Aktifkan premium')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->modalDescription('Aktivasi manual, misalnya setelah pembayaran diterima di luar aplikasi.')
            ->schema([
                DatePicker::make('expires_at')
                    ->label('Berlaku sampai')
                    ->helperText('Kosongkan untuk premium tanpa batas waktu.')
                    ->after('today'),
            ])
            ->action(function (User $record, array $data): void {
                $expiresAt = filled($data['expires_at'] ?? null)
                    ? CarbonImmutable::parse($data['expires_at'])->endOfDay()
                    : null;

                $record->subscribeTo(SubscriptionPlan::Premium, $expiresAt);

                $this->successNotificationTitle("{$record->name} sekarang premium");
                $this->success();
            });
    }
}
