<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Laporan yang dihitung dari salinan buku di dalam ponsel.
 *
 * Tidak perlu sinyal sama sekali. Yang dihitung hanya catatan yang terlihat,
 * jadi penghapusan yang belum sempat terkirim pun sudah tidak ikut dijumlah.
 */
class LedgerReport
{
    /**
     * @return array{income: float, expense: float, net: float}
     */
    public function totals(CarbonInterface $from, CarbonInterface $until): array
    {
        $totals = Transaction::query()
            ->visible()
            ->whereBetween('transaction_date', [$from->toDateString(), $until->toDateString()])
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (float) ($totals['income'] ?? 0);
        $expense = (float) ($totals['expense'] ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    /**
     * Total per kategori, dari yang terbesar.
     *
     * @return Collection<int, array{category: string, total: float}>
     */
    public function totalsByCategory(string $type, CarbonInterface $from, CarbonInterface $until): Collection
    {
        return Transaction::query()
            ->visible()
            ->where('type', $type)
            ->whereBetween('transaction_date', [$from->toDateString(), $until->toDateString()])
            ->selectRaw('category_public_id, SUM(amount) as total')
            ->groupBy('category_public_id')
            ->with('category')
            ->get()
            ->map(fn (Transaction $row): array => [
                'category' => $row->category->name ?? 'Tanpa kategori',
                'total' => (float) $row->total,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Pengeluaran per kategori, dikunci dengan public_id kategorinya supaya anggaran
     * bisa mencocokkan sendiri tanpa satu kueri untuk tiap baris.
     *
     * @return array<string, float>
     */
    public function spendingByCategoryId(CarbonInterface $from, CarbonInterface $until): array
    {
        return Transaction::query()
            ->visible()
            ->where('type', 'expense')
            ->whereNotNull('category_public_id')
            ->whereBetween('transaction_date', [$from->toDateString(), $until->toDateString()])
            ->selectRaw('category_public_id, SUM(amount) as total')
            ->groupBy('category_public_id')
            ->pluck('total', 'category_public_id')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * Catatan tertua yang dipegang ponsel. Dipakai untuk mengaku jujur kalau
     * bulan yang diminta berada di luar salinan yang ada di sini.
     */
    public function earliestDate(): ?CarbonImmutable
    {
        $earliest = Transaction::query()->visible()->min('transaction_date');

        return $earliest === null ? null : CarbonImmutable::parse($earliest);
    }
}
