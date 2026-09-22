<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Observers\UserObserver;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Langganan terbaru milik user, dipakai sebagai acuan plan saat ini.
     *
     * @return HasOne<Subscription, $this>
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function isPremium(): bool
    {
        return $this->currentSubscription?->isActivePremium() ?? false;
    }

    public function canCreateBook(): bool
    {
        return $this->isPremium() || $this->books()->doesntExist();
    }

    /**
     * Ganti plan user: langganan aktif sebelumnya dibatalkan agar riwayat tetap tersimpan.
     */
    public function subscribeTo(SubscriptionPlan $plan, ?CarbonInterface $expiresAt = null): Subscription
    {
        $this->subscriptions()
            ->where('status', SubscriptionStatus::Active)
            ->update(['status' => SubscriptionStatus::Cancelled]);

        $subscription = $this->subscriptions()->create([
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $this->setRelation('currentSubscription', $subscription);

        return $subscription;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_admin,
            default => true,
        };
    }

    /**
     * @return Collection<int, Book>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->books;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Book && $tenant->user_id === $this->id;
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->books()->orderByDesc('is_default')->oldest('id')->first();
    }
}
