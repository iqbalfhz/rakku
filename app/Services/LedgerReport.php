<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Book;
use App\Models\Transaction;
use App\Support\Percentage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Laporan keuangan per buku. Transfer tidak ikut dihitung karena bersifat netral.
 */
class LedgerReport
{
    public function __construct(private Book $book) {}

    /**
     * @return array{income: float, expense: float, net: float}
     */
    public function totals(CarbonInterface $from, CarbonInterface $until): array
    {
        $totals = $this->book->transactions()
            ->betweenDates($from, $until)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (float) ($totals[TransactionType::Income->value] ?? 0);
        $expense = (float) ($totals[TransactionType::Expense->value] ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    /**
     * Arus kas per bulan dalam satu tahun.
     *
     * @return list<array{label: string, income: float, expense: float}>
     */
    public function monthlyCashFlow(int $year): array
    {
        $start = CarbonImmutable::create($year)->startOfYear();

        return $this->cashFlowSeries($start, $start->endOfYear(), '1 month', 'Y-m', 'M');
    }

    /**
     * Arus kas per bulan untuk N bulan terakhir, termasuk bulan berjalan.
     *
     * @return list<array{label: string, income: float, expense: float}>
     */
    public function recentMonthlyCashFlow(int $months = 12): array
    {
        $until = today()->endOfMonth();

        return $this->cashFlowSeries($until->subMonthsNoOverflow($months - 1)->startOfMonth(), $until, '1 month', 'Y-m', 'M y');
    }

    /**
     * Arus kas per hari dalam satu bulan.
     *
     * @return list<array{label: string, income: float, expense: float}>
     */
    public function dailyCashFlow(CarbonInterface $month): array
    {
        return $this->cashFlowSeries($month->startOfMonth(), $month->endOfMonth(), '1 day', 'Y-m-d', 'j');
    }

    /**
     * Total per kategori, diurutkan dari yang terbesar.
     *
     * @return Collection<int, array{category: string, total: float}>
     */
    public function totalsByCategory(TransactionType $type, CarbonInterface $from, CarbonInterface $until): Collection
    {
        return $this->book->transactions()
            ->ofType($type)
            ->betweenDates($from, $until)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(fn (Transaction $row): array => [
                'category' => $row->category->name ?? 'Tanpa Kategori',
                'total' => (float) $row->total,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Perbandingan total per kategori antara bulan terpilih dan bulan sebelumnya.
     *
     * @return Collection<int, array{category: string, current: float, previous: float, change_percent: float|null}>
     */
    public function categoryComparison(TransactionType $type, CarbonInterface $month): Collection
    {
        $previousMonth = $month->subMonthNoOverflow();

        $current = $this->totalsByCategory($type, $month->startOfMonth(), $month->endOfMonth())->pluck('total', 'category');
        $previous = $this->totalsByCategory($type, $previousMonth->startOfMonth(), $previousMonth->endOfMonth())->pluck('total', 'category');

        return $current->keys()
            ->merge($previous->keys())
            ->unique()
            ->map(fn (string $category): array => [
                'category' => $category,
                'current' => $current[$category] ?? 0.0,
                'previous' => $previous[$category] ?? 0.0,
                'change_percent' => Percentage::change($current[$category] ?? 0.0, $previous[$category] ?? 0.0),
            ])
            ->sortByDesc('current')
            ->values();
    }

    /**
     * Kelompokkan total harian ke dalam bucket (hari/bulan) agar query tetap netral terhadap jenis database.
     *
     * @return list<array{label: string, income: float, expense: float}>
     */
    private function cashFlowSeries(CarbonInterface $from, CarbonInterface $until, string $interval, string $bucketFormat, string $labelFormat): array
    {
        $buckets = [];

        foreach (CarbonPeriod::create($from->startOfDay(), $interval, $until) as $date) {
            $buckets[$date->format($bucketFormat)] = [
                'label' => $date->translatedFormat($labelFormat),
                'income' => 0.0,
                'expense' => 0.0,
            ];
        }

        $dailyTotals = $this->book->transactions()
            ->betweenDates($from, $until)
            ->selectRaw('transaction_date, type, SUM(amount) as total')
            ->groupBy('transaction_date', 'type')
            ->toBase()
            ->get();

        foreach ($dailyTotals as $row) {
            $bucket = CarbonImmutable::parse($row->transaction_date)->format($bucketFormat);

            if (isset($buckets[$bucket])) {
                $buckets[$bucket][$row->type] += (float) $row->total;
            }
        }

        return array_values($buckets);
    }
}
