<?php

namespace App\Filament\App\Widgets;

use App\Enums\TransactionType;
use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Support\Rupiah;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Insight dasar: kategori pengeluaran terbesar bulan berjalan.
 */
class TopExpenseCategoriesChart extends ChartWidget
{
    use InteractsWithLedgerReport;

    private const int VISIBLE_CATEGORIES = 5;

    private const array COLORS = ['#ef4444', '#f97316', '#f59e0b', '#8b5cf6', '#3b82f6', '#9ca3af'];

    protected static ?int $sort = 3;

    protected ?string $heading = 'Pengeluaran terbesar bulan ini';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    /**
     * @var Collection<int, array{category: string, total: float}>|null
     */
    private ?Collection $categoryTotals = null;

    public function getDescription(): ?string
    {
        $totals = $this->categoryTotals();
        $grandTotal = $totals->sum('total');

        if ($grandTotal <= 0) {
            return 'Belum ada pengeluaran bulan ini.';
        }

        $largest = $totals->first();

        return sprintf(
            '%s menyerap %s%% pengeluaran (%s).',
            $largest['category'],
            round($largest['total'] / $grandTotal * 100),
            Rupiah::format($largest['total']),
        );
    }

    protected function getData(): array
    {
        $totals = $this->categoryTotals();
        $visible = $totals->take(self::VISIBLE_CATEGORIES);
        $othersTotal = $totals->skip(self::VISIBLE_CATEGORIES)->sum('total');

        if ($othersTotal > 0) {
            $visible->push(['category' => 'Lainnya', 'total' => $othersTotal]);
        }

        return [
            'datasets' => [
                [
                    'data' => $visible->pluck('total')->all(),
                    'backgroundColor' => array_slice(self::COLORS, 0, $visible->count()),
                ],
            ],
            'labels' => $visible->pluck('category')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return Collection<int, array{category: string, total: float}>
     */
    private function categoryTotals(): Collection
    {
        return $this->categoryTotals ??= $this->ledgerReport()->totalsByCategory(
            TransactionType::Expense,
            today()->startOfMonth(),
            today()->endOfMonth(),
        );
    }
}
