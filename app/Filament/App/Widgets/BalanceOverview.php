<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Models\Book;
use App\Support\Rupiah;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BalanceOverview extends StatsOverviewWidget
{
    use InteractsWithLedgerReport;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        /** @var Book $book */
        $book = Filament::getTenant();
        $totals = $this->ledgerReport()->totals(today()->startOfMonth(), today()->endOfMonth());

        return [
            Stat::make('Total saldo', Rupiah::format((float) $book->accounts()->sum('current_balance')))
                ->description($book->accounts()->count().' akun')
                ->descriptionIcon(Heroicon::OutlinedWallet)
                ->color('primary'),
            Stat::make('Pemasukan bulan ini', Rupiah::format($totals['income']))
                ->descriptionIcon(Heroicon::OutlinedArrowDownLeft)
                ->description(today()->translatedFormat('F Y'))
                ->color('success'),
            Stat::make('Pengeluaran bulan ini', Rupiah::format($totals['expense']))
                ->descriptionIcon(Heroicon::OutlinedArrowUpRight)
                ->description(today()->translatedFormat('F Y'))
                ->color('danger'),
            Stat::make('Arus kas bersih', Rupiah::format($totals['net']))
                ->description($totals['net'] >= 0 ? 'Surplus' : 'Defisit')
                ->color($totals['net'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
