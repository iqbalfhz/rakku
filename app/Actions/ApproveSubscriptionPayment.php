<?php

namespace App\Actions;

use App\Enums\SubscriptionPaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentApproved;

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

        $payment->user->notify(new SubscriptionPaymentApproved($payment, $expiresAt));
    }
}
