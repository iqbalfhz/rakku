<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashFlowOverview extends StatsOverviewWidget
{
    use InteractsWithLedgerReport;
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return 'Arus kas '.$this->reportPeriod()->label();
    }

    protected function getStats(): array
    {
        $period = $this->reportPeriod();
        $totals = $this->ledgerReport()->totals($period->from, $period->until);

        return [
            Stat::make('Uang masuk', $this->formatRupiah($totals['income']))
                ->color('success'),
            Stat::make('Uang keluar', $this->formatRupiah($totals['expense']))
                ->color('danger'),
            Stat::make('Arus kas bersih', $this->formatRupiah($totals['net']))
                ->description($totals['net'] >= 0 ? 'Surplus' : 'Defisit')
                ->color($totals['net'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
