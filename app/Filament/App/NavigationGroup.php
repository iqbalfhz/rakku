<?php

namespace App\Filament\App;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Transactions;
    case MasterData;
    case DebtsAndInvoices;
    case Reports;

    public function getLabel(): string
    {
        return match ($this) {
            self::Transactions => 'Transaksi',
            self::MasterData => 'Data Buku',
            self::DebtsAndInvoices => 'Utang & Invoice',
            self::Reports => 'Laporan',
        };
    }
}
