<?php

namespace App\Filament\App\Widgets;

use App\Enums\TransactionType;
use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Models\User;
use App\Support\Percentage;
use App\Support\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Insight lanjutan (premium): perbandingan bulan ini dengan bulan lalu.
 */
class MonthOverMonthOverview extends StatsOverviewWidget
{
    use InteractsWithLedgerReport;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Dibanding bulan lalu';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isPremium();
    }

    protected function getStats(): array
    {
        $report = $this->ledgerReport();
        $lastMonth = today()->subMonthNoOverflow();
        $current = $report->totals(today()->startOfMonth(), today()->endOfMonth());
        $previous = $report->totals($lastMonth->startOfMonth(), $lastMonth->endOfMonth());

        $risingCategory = $report->categoryComparison(TransactionType::Expense, today())
            ->filter(fn (array $row): bool => $row['change_percent'] !== null && $row['change_percent'] > 0)
            ->sortByDesc('change_percent')
            ->first();

        return [
            $this->comparisonStat('Pemasukan', $current['income'], $previous['income'], increaseIsGood: true),
            $this->comparisonStat('Pengeluaran', $current['expense'], $previous['expense'], increaseIsGood: false),
            Stat::make('Kenaikan tertinggi', $risingCategory['category'] ?? '-')
                ->description($risingCategory === null
                    ? 'Tidak ada kategori yang naik'
                    : sprintf('+%s%% (%s)', $risingCategory['change_percent'], Rupiah::format($risingCategory['current'])))
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color($risingCategory === null ? 'gray' : 'warning'),
        ];
    }

    private function comparisonStat(string $label, float $current, float $previous, bool $increaseIsGood): Stat
    {
        $changePercent = Percentage::change($current, $previous);
        $isIncrease = $current >= $previous;

        return Stat::make($label, Rupiah::format($current))
            ->description($changePercent === null
                ? 'Bulan lalu '.Rupiah::format($previous)
                : sprintf('%s%s%% dari %s', $isIncrease ? '+' : '', $changePercent, Rupiah::format($previous)))
            ->descriptionIcon($isIncrease ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->color($isIncrease === $increaseIsGood ? 'success' : 'danger');
    }
}
