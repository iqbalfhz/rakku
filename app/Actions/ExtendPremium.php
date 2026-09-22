<?php

namespace App\Actions;

use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

class ExtendPremium
{
    /**
     * Aktifkan premium selama satu paket dan kembalikan tanggal berakhirnya.
     */
    public function handle(User $user, SubscriptionPackage $package): ?CarbonImmutable
    {
        $expiresAt = $this->expiryAfter($user, $package);

        $user->subscribeTo(SubscriptionPlan::Premium, $expiresAt);

        return $expiresAt;
    }

    /**
     * Sisa masa aktif tidak hangus: perpanjangan dihitung dari tanggal kedaluwarsa yang ada.
     * Premium tanpa batas waktu dibiarkan apa adanya agar tidak malah dipersingkat.
     */
    public function expiryAfter(User $user, SubscriptionPackage $package): ?CarbonImmutable
    {
        $subscription = $user->currentSubscription;
        $isActivePremium = $subscription instanceof Subscription && $subscription->isActivePremium();
        $activeExpiry = $isActivePremium ? $subscription->expires_at : null;

        if ($isActivePremium && $activeExpiry === null) {
            return null;
        }

        $start = $activeExpiry === null ? CarbonImmutable::now() : CarbonImmutable::parse($activeExpiry);

        return $start->addMonths($package->months())->endOfDay();
    }
}
