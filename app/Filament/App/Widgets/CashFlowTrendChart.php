<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use Filament\Widgets\ChartWidget;

class CashFlowTrendChart extends ChartWidget
{
    use InteractsWithLedgerReport;

    public const string INCOME_COLOR = '#10b981';

    public const string EXPENSE_COLOR = '#ef4444';

    protected static ?int $sort = 2;

    protected ?string $heading = 'Tren arus kas 12 bulan terakhir';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return self::cashFlowDatasets($this->ledgerReport()->recentMonthlyCashFlow());
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Ubah deret arus kas menjadi dataset Chart.js pemasukan vs pengeluaran.
     *
     * @param  list<array{label: string, income: float, expense: float}>  $series
     * @return array<string, mixed>
     */
    public static function cashFlowDatasets(array $series): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => array_column($series, 'income'),
                    'backgroundColor' => self::INCOME_COLOR,
                    'borderColor' => self::INCOME_COLOR,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => array_column($series, 'expense'),
                    'backgroundColor' => self::EXPENSE_COLOR,
                    'borderColor' => self::EXPENSE_COLOR,
                ],
            ],
            'labels' => array_column($series, 'label'),
        ];
    }
}
