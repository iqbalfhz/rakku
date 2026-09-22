<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DebtType: string implements HasColor, HasLabel
{
    case Receivable = 'receivable';
    case Payable = 'payable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receivable => 'Piutang',
            self::Payable => 'Utang',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Receivable => 'success',
            self::Payable => 'danger',
        };
    }

    /**
     * Jenis transaksi yang tercatat saat cicilan dibayar:
     * piutang dibayar = uang masuk, utang dibayar = uang keluar.
     */
    public function paymentTransactionType(): TransactionType
    {
        return match ($this) {
            self::Receivable => TransactionType::Income,
            self::Payable => TransactionType::Expense,
        };
    }
}
