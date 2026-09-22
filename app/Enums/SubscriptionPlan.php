<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionPlan: string implements HasColor, HasLabel
{
    case Free = 'free';
    case Premium = 'premium';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Premium => 'Premium',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Free => 'gray',
            self::Premium => 'warning',
        };
    }
}
