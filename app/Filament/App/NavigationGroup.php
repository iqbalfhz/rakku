<?php

namespace App\Filament\App;

use Filament\Support\Contracts\HasLabel;

/**
 * Grup menu sidebar; urutan case menentukan urutan tampil.
 */
enum NavigationGroup implements HasLabel
{
    case Transactions;
    case ReportsAndBudgets;
    case DebtsAndInvoices;
    case BookSettings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Transactions => 'Transaksi',
            self::ReportsAndBudgets => 'Laporan & Anggaran',
            self::DebtsAndInvoices => 'Utang & Invoice',
            self::BookSettings => 'Pengaturan Buku',
        };
    }
}
