<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan', 'status', 'started_at', 'expires_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActivePremium(): bool
    {
        return $this->plan === SubscriptionPlan::Premium
            && $this->status === SubscriptionStatus::Active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Versi query dari isActivePremium().
     *
     * @param  Builder<Subscription>  $query
     */
    #[Scope]
    protected function activePremium(Builder $query): void
    {
        $query->where('plan', SubscriptionPlan::Premium)
            ->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
