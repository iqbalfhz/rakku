<?php

namespace App\Enums;

use App\Support\Rupiah;
use App\Support\SubscriptionConfig;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionPackage: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }

    public function price(): int
    {
        return SubscriptionConfig::priceFor($this);
    }

    public function getLabel(): string
    {
        return "{$this->months()} bulan — ".Rupiah::format($this->price());
    }
}
