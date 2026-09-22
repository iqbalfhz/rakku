<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CashFlowChart extends ChartWidget
{
    use InteractsWithLedgerReport;
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): ?string
    {
        return $this->reportPeriod()->isYearly ? 'Arus kas per bulan' : 'Arus kas per hari';
    }

    protected function getData(): array
    {
        $period = $this->reportPeriod();
        $report = $this->ledgerReport();

        return CashFlowTrendChart::cashFlowDatasets($period->isYearly
            ? $report->monthlyCashFlow($period->from->year)
            : $report->dailyCashFlow($period->from));
    }

    protected function getType(): string
    {
        return 'line';
    }
}
