<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Terkirim',
            self::Paid => 'Lunas',
            self::Overdue => 'Telat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Sent, self::Overdue], true);
    }
}
