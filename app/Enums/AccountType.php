<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AccountType: string implements HasIcon, HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case EWallet = 'e-wallet';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Bank => 'Bank',
            self::EWallet => 'E-Wallet',
            self::Other => 'Lainnya',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Cash => Heroicon::OutlinedBanknotes,
            self::Bank => Heroicon::OutlinedBuildingLibrary,
            self::EWallet => Heroicon::OutlinedDevicePhoneMobile,
            self::Other => Heroicon::OutlinedWallet,
        };
    }
}
