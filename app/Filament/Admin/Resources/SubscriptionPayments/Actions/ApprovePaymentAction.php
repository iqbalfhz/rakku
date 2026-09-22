<?php

namespace App\Filament\Admin\Resources\SubscriptionPayments\Actions;

use App\Actions\ApproveSubscriptionPayment;
use App\Models\SubscriptionPayment;
use App\Support\Rupiah;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ApprovePaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approvePayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Setujui')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (SubscriptionPayment $record): bool => $record->isPending())
            ->requiresConfirmation()
            ->modalDescription(fn (SubscriptionPayment $record): string => sprintf(
                'Pastikan transfer %s dari %s benar-benar masuk. Premium akan aktif %d bulan.',
                Rupiah::format((float) $record->amount),
                $record->user->name,
                $record->package->months(),
            ))
            ->action(function (SubscriptionPayment $record): void {
                app(ApproveSubscriptionPayment::class)->handle($record, auth()->user());

                $this->successNotificationTitle("Premium {$record->user->name} aktif");
                $this->success();
            });
    }
}
