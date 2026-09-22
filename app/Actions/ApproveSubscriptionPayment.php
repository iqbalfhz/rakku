<?php

namespace App\Actions;

use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;

class ApproveSubscriptionPayment
{
    /**
     * Setujui bukti transfer: premium diaktifkan lalu pengguna diberi tahu.
     */
    public function handle(SubscriptionPayment $payment, User $reviewer): void
    {
        $expiresAt = $this->expiryAfter($payment);

        $payment->user->subscribeTo(SubscriptionPlan::Premium, $expiresAt);

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

    /**
     * Sisa masa aktif tidak hangus: perpanjangan dihitung dari tanggal kedaluwarsa yang ada.
     * Premium tanpa batas waktu dibiarkan apa adanya agar tidak malah dipersingkat.
     */
    private function expiryAfter(SubscriptionPayment $payment): ?CarbonImmutable
    {
        $subscription = $payment->user->currentSubscription;
        $activeExpiry = $subscription instanceof Subscription && $subscription->isActivePremium()
            ? $subscription->expires_at
            : null;

        if ($activeExpiry === null && $subscription?->isActivePremium()) {
            return null;
        }

        $start = $activeExpiry === null ? CarbonImmutable::now() : CarbonImmutable::parse($activeExpiry);

        return $start->addMonths($payment->package->months())->endOfDay();
    }
}
