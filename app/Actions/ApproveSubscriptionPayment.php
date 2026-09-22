<?php

namespace App\Actions;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Filament\Notifications\Notification;

class ApproveSubscriptionPayment
{
    public function __construct(private ExtendPremium $extendPremium) {}

    /**
     * Setujui bukti transfer: premium diaktifkan lalu pengguna diberi tahu.
     */
    public function handle(SubscriptionPayment $payment, User $reviewer): void
    {
        $expiresAt = $this->extendPremium->handle($payment->user, $payment->package);

        $payment->forceFill([
            'status' => SubscriptionPaymentStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        Notification::make()
            ->success()
            ->title('Premium aktif')
            ->body($expiresAt === null
                ? 'Pembayaran Anda sudah diverifikasi. Premium Anda tetap berlaku tanpa batas waktu.'
                : "Pembayaran Anda sudah diverifikasi. Premium berlaku sampai {$expiresAt->translatedFormat('j F Y')}.")
            ->sendToDatabase($payment->user);
    }
}
