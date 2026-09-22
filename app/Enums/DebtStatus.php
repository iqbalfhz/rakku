<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DebtStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum Lunas',
            self::Paid => 'Lunas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
        };
    }
}
