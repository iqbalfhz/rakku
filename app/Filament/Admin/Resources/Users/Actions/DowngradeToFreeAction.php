<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class DowngradeToFreeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downgradeToFree';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kembalikan ke free')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->visible(fn (User $record): bool => $record->isPremium())
            ->requiresConfirmation()
            ->modalDescription('Data premium (buku tambahan, utang-piutang, invoice) tetap tersimpan tetapi tidak bisa diakses sampai premium aktif lagi.')
            ->action(function (User $record): void {
                $record->subscribeTo(SubscriptionPlan::Free);

                $this->successNotificationTitle("{$record->name} dikembalikan ke plan free");
                $this->success();
            });
    }
}
