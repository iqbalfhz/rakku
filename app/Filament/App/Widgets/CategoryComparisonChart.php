<?php

namespace App\Filament\App\Widgets;

use App\Enums\TransactionType;
use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Models\User;
use Filament\Widgets\ChartWidget;

/**
 * Insight lanjutan (premium): pengeluaran per kategori bulan ini vs bulan lalu.
 */
class CategoryComparisonChart extends ChartWidget
{
    use InteractsWithLedgerReport;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Pengeluaran per kategori: bulan ini vs bulan lalu';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isPremium();
    }

    protected function getData(): array
    {
        $comparison = $this->ledgerReport()->categoryComparison(TransactionType::Expense, today())->take(8);

        return [
            'datasets' => [
                [
                    'label' => today()->subMonthNoOverflow()->translatedFormat('F'),
                    'data' => $comparison->pluck('previous')->all(),
                    'backgroundColor' => '#9ca3af',
                ],
                [
                    'label' => today()->translatedFormat('F'),
                    'data' => $comparison->pluck('current')->all(),
                    'backgroundColor' => CashFlowTrendChart::EXPENSE_COLOR,
                ],
            ],
            'labels' => $comparison->pluck('category')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
