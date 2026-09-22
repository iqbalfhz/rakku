<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Support\Rupiah;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitLossOverview extends StatsOverviewWidget
{
    use InteractsWithLedgerReport;
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return 'Laba-rugi '.$this->reportPeriod()->label();
    }

    protected function getStats(): array
    {
        $period = $this->reportPeriod();
        $totals = $this->ledgerReport()->totals($period->from, $period->until);
        $margin = $totals['income'] > 0 ? round($totals['net'] / $totals['income'] * 100, 1) : null;

        return [
            Stat::make('Total pendapatan', Rupiah::format($totals['income']))
                ->color('success'),
            Stat::make('Total beban', Rupiah::format($totals['expense']))
                ->color('danger'),
            Stat::make($totals['net'] >= 0 ? 'Laba bersih' : 'Rugi bersih', Rupiah::format(abs($totals['net'])))
                ->description($margin === null ? 'Belum ada pendapatan' : "Margin {$margin}%")
                ->color($totals['net'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
