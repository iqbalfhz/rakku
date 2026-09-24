<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Menjaga fitur premium di dalam ponsel.
 *
 * Statusnya disalin dari server saat sinkron, lalu dihitung ulang di sini setiap
 * kali ditanya — supaya premium yang habis tetap terkunci walau ponsel sudah
 * berhari-hari tanpa sinyal dan belum sempat mendengar kabar dari server.
 */
class PlanGate
{
    private const string IS_PREMIUM = 'plan.is_premium';

    private const string EXPIRES_AT = 'plan.expires_at';

    public function isPremium(): bool
    {
        if (Setting::read(self::IS_PREMIUM) !== '1') {
            return false;
        }

        $expiresAt = Setting::read(self::EXPIRES_AT);

        return $expiresAt === null || CarbonImmutable::parse($expiresAt)->isFuture();
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $expiresAt = Setting::read(self::EXPIRES_AT);

        return $expiresAt === null ? null : CarbonImmutable::parse($expiresAt);
    }

    /**
     * @param  array{is_premium: bool, expires_at: string|null}  $plan
     */
    public function remember(array $plan): void
    {
        Setting::write(self::IS_PREMIUM, $plan['is_premium'] ? '1' : '0');
        Setting::write(self::EXPIRES_AT, $plan['expires_at']);
    }

    public function forget(): void
    {
        Setting::forget(self::IS_PREMIUM);
        Setting::forget(self::EXPIRES_AT);
    }
}
