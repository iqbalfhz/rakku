<?php

namespace App\Support;

use App\Enums\SubscriptionPackage;
use App\Models\Setting;
use Illuminate\Support\Arr;

/**
 * Rekening tujuan transfer dan harga paket premium: nilai yang disimpan admin lewat
 * panel selalu menang, dan config/subscription.php hanya dipakai sebagai cadangan.
 */
class SubscriptionConfig
{
    public const string BANK_KEY = 'subscription.bank';

    public const string PRICES_KEY = 'subscription.prices';

    /**
     * @var list<string>
     */
    public const array BANK_FIELDS = ['name', 'account_number', 'account_holder'];

    /**
     * @return array{name: string, account_number: string, account_holder: string}
     */
    public static function bank(): array
    {
        $stored = Arr::only((array) Setting::get(self::BANK_KEY, []), self::BANK_FIELDS);

        return array_merge(config('subscription.bank'), array_filter($stored, 'filled'));
    }

    /**
     * @return array<string, int>
     */
    public static function prices(): array
    {
        $stored = (array) Setting::get(self::PRICES_KEY, []);

        return array_merge(config('subscription.prices'), array_map('intval', array_filter($stored, 'filled')));
    }

    public static function priceFor(SubscriptionPackage $package): int
    {
        return self::prices()[$package->value];
    }

    /**
     * @param  array<string, string>  $bank
     * @param  array<string, int>  $prices
     */
    public static function save(array $bank, array $prices): void
    {
        Setting::put(self::BANK_KEY, Arr::only($bank, self::BANK_FIELDS));
        Setting::put(self::PRICES_KEY, array_map('intval', Arr::only($prices, array_column(SubscriptionPackage::cases(), 'value'))));
    }
}
