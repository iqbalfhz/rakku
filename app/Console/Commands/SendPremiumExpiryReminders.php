<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Notifications\PremiumExpired;
use App\Notifications\PremiumExpiring;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('app:send-premium-expiry-reminders')]
#[Description('Ingatkan pengguna sebelum premiumnya habis, dan jelaskan saat masa aktifnya berakhir')]
class SendPremiumExpiryReminders extends Command
{
    /**
     * Jumlah hari sebelum kedaluwarsa saat pengingat dikirim; 0 berarti hari terakhir.
     *
     * @var list<int>
     */
    public const array REMINDER_DAYS_BEFORE_EXPIRY = [7, 1, 0];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reminderCount = 0;

        foreach (self::REMINDER_DAYS_BEFORE_EXPIRY as $daysLeft) {
            $this->premiumExpiringOn($daysLeft)->each(function (User $user) use ($daysLeft, &$reminderCount): void {
                $user->notify(new PremiumExpiring($daysLeft));

                $reminderCount++;
            });
        }

        $expiredCount = $this->markExpiredPremium();

        $this->info("{$reminderCount} pengingat terkirim, {$expiredCount} premium ditandai berakhir.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function premiumExpiringOn(int $daysLeft): Collection
    {
        return User::query()
            ->whereHas('currentSubscription', fn (Builder $subscription) => $subscription
                ->activePremium()
                ->whereDate('expires_at', today()->addDays($daysLeft)))
            ->get();
    }

    /**
     * Sehari setelah kedaluwarsa, langganan ditutup dan pengguna diberi tahu sekali.
     */
    private function markExpiredPremium(): int
    {
        $expiredCount = 0;

        User::query()
            ->whereHas('currentSubscription', fn (Builder $subscription) => $subscription
                ->where('plan', SubscriptionPlan::Premium)
                ->where('status', SubscriptionStatus::Active)
                ->whereDate('expires_at', today()->subDay()))
            ->with('currentSubscription')
            ->each(function (User $user) use (&$expiredCount): void {
                $user->currentSubscription->update(['status' => SubscriptionStatus::Expired]);

                $user->notify(new PremiumExpired);

                $expiredCount++;
            });

        return $expiredCount;
    }
}
