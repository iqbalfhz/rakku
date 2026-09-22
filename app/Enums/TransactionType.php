<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TransactionType: string implements HasColor, HasIcon, HasLabel
{
    case Income = 'income';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Income => Heroicon::OutlinedArrowDownLeft,
            self::Expense => Heroicon::OutlinedArrowUpRight,
        };
    }

    /**
     * Pengali saldo akun: pemasukan menambah, pengeluaran mengurangi.
     */
    public function balanceDirection(): int
    {
        return match ($this) {
            self::Income => 1,
            self::Expense => -1,
        };
    }
}
