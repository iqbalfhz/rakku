<?php

use App\Services\LedgerReport;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * Cukup panjang untuk memperlihatkan arah, cukup pendek untuk muat di layar
     * ponsel tanpa batang yang saling berimpit.
     */
    private const int TREND_MONTHS = 6;

    public string $month = '';

    public function mount(): void
    {
        $this->month = today()->format('Y-m');
    }

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    public function monthLabel(): string
    {
        return $this->from()->translatedFormat('F Y');
    }

    /**
     * @return array{income: float, expense: float, net: float}
     */
    public function totals(): array
    {
        return app(LedgerReport::class)->totals($this->from(), $this->until());
    }

    /**
     * Margin hanya bermakna kalau ada pendapatan untuk dibandingkan.
     */
    public function margin(): ?float
    {
        $totals = $this->totals();

        return $totals['income'] > 0 ? round($totals['net'] / $totals['income'] * 100, 1) : null;
    }

    /**
     * @return Collection<int, array{category: string, total: float}>
     */
    public function byCategory(string $type): Collection
    {
        return app(LedgerReport::class)->totalsByCategory($type, $this->from(), $this->until());
    }

    /**
     * Deret untuk grafik tren, lengkap dengan tinggi batang dalam persen.
     *
     * Tingginya dihitung di sini, bukan di CSS, dan keduanya diskalakan terhadap
     * satu nilai terbesar yang sama — kalau masing-masing diskalakan sendiri,
     * batang masuk dan keluar tidak lagi bisa dibandingkan.
     *
     * @return list<array{label: string, short: string, income: string, expense: string, incomeHeight: float, expenseHeight: float}>
     */
    public function trend(): array
    {
        $series = app(LedgerReport::class)->monthlyTotals($this->until(), self::TREND_MONTHS);

        $peak = max((float) $series->max('income'), (float) $series->max('expense'));

        return $series->map(fn (array $row): array => [
            'label' => $row['month']->translatedFormat('F Y'),
            'short' => $row['month']->translatedFormat('M'),
            'income' => Rupiah::format($row['income']),
            'expense' => Rupiah::format($row['expense']),
            'incomeHeight' => $this->barHeight($row['income'], $peak),
            'expenseHeight' => $this->barHeight($row['expense'], $peak),
        ])->all();
    }

    /**
     * Bulan bernilai nol tidak digambar sama sekali.
     *
     * Batang setipis apa pun tetap terbaca sebagai "ada uang masuk", dan lima
     * bulan kosong berjajar jadi terlihat seperti aktivitas kecil yang tidak
     * pernah terjadi. Sebaliknya nominal kecil yang bukan nol disisakan tinggi
     * minimum supaya tidak hilang di bawah garis dasar.
     */
    private function barHeight(float $amount, float $peak): float
    {
        if ($amount <= 0 || $peak <= 0) {
            return 0.0;
        }

        return max(round($amount / $peak * 100, 2), 1.5);
    }

    public function shareOf(float $total, float $amount): int
    {
        return $total > 0 ? (int) round($amount / $total * 100) : 0;
    }

    /**
     * Bulan yang diminta ada di luar salinan yang dipegang ponsel, jadi angka nol
     * di sini belum tentu berarti tidak ada transaksi.
     */
    public function isBeyondLocalCopy(): bool
    {
        $earliest = app(LedgerReport::class)->earliestDate();

        return $earliest !== null && $this->until()->lt($earliest);
    }

    private function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->month.'-01')->startOfMonth();
    }

    private function until(): CarbonImmutable
    {
        return $this->from()->endOfMonth();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Laporan</p>
        <h1 class="masthead__title">Sebulan dalam<br>satu halaman.</h1>
        <p class="masthead__note">Dihitung dari catatan yang ada di ponsel, tanpa perlu sinyal.</p>
    </header>

    <div class="field">
        <label class="field__label" for="month">Bulan</label>
        <input class="field__input" id="month" type="month" wire:model.live="month">
    </div>

    @if ($this->isBeyondLocalCopy())
        <p class="notice">Bulan ini lebih tua daripada catatan yang dipegang ponsel. Buka laporan lengkapnya lewat web.</p>
    @endif

    @php ($totals = $this->totals())

    <section class="tape">
        <p class="tape__label">Arus kas {{ $this->monthLabel() }}</p>

        <p class="numeral {{ $totals['net'] >= 0 ? 'numeral--credit' : 'numeral--debit' }}">
            {{ $totals['net'] < 0 ? '−' : '' }}{{ Rupiah::format(abs($totals['net'])) }}
        </p>
        <p style="margin: 4px 0 0;">
            <span class="stamp {{ $totals['net'] >= 0 ? '' : 'stamp--due' }}">{{ $totals['net'] >= 0 ? 'Surplus' : 'Defisit' }}</span>
        </p>

        <div class="entry" style="margin-top: 20px;">
            <div class="entry__label">
                <p class="entry__title">Uang masuk</p>
            </div>
            <span class="entry__amount numeral--credit">{{ Rupiah::format($totals['income']) }}</span>
        </div>

        <div class="entry">
            <div class="entry__label">
                <p class="entry__title">Uang keluar</p>
            </div>
            <span class="entry__amount numeral--debit">{{ Rupiah::format($totals['expense']) }}</span>
        </div>
    </section>

    @if (! $this->isPremium())
        <section class="tape">
            <p class="tape__label">Fitur premium</p>
            <p class="entry__title" style="margin: 8px 0 0;">Laba-rugi dan rincian per kategori terbuka setelah langganan aktif.</p>
            <a class="button" href="{{ route('subscription') }}" wire:navigate style="margin-top: 18px;">Aktifkan premium</a>
        </section>
    @else
        @php ($trend = $this->trend())

        <section class="tape">
            <p class="tape__label">Enam bulan terakhir</p>

            @if (collect($trend)->every(fn (array $row): bool => $row['incomeHeight'] == 0 && $row['expenseHeight'] == 0))
                <p class="entry__title muted" style="margin: 10px 0 0;">Belum ada catatan di rentang ini.</p>
            @else
                <div class="chart" x-data="{ picked: null, months: @js($trend) }">
                    <div class="chart__plot">
                        @foreach ($trend as $index => $row)
                            <button type="button"
                                    class="chart__month"
                                    :class="picked === {{ $index }} ? 'chart__month--picked' : ''"
                                    x-on:click="picked = picked === {{ $index }} ? null : {{ $index }}"
                                    aria-label="{{ $row['label'] }}: masuk {{ $row['income'] }}, keluar {{ $row['expense'] }}">
                                <span class="chart__bar chart__bar--income" style="height: {{ $row['incomeHeight'] }}%"></span>
                                <span class="chart__bar chart__bar--expense" style="height: {{ $row['expenseHeight'] }}%"></span>
                            </button>
                        @endforeach
                    </div>

                    <div class="chart__ticks">
                        @foreach ($trend as $row)
                            <span class="chart__tick">{{ $row['short'] }}</span>
                        @endforeach
                    </div>

                    <p class="chart__legend">
                        <span class="chart__key"><span class="chart__swatch" style="background: var(--chart-income);"></span>Masuk</span>
                        <span class="chart__key"><span class="chart__swatch" style="background: var(--chart-expense);"></span>Keluar</span>
                    </p>

                    <p class="chart__readout"
                       x-text="picked === null
                            ? 'Ketuk satu bulan untuk melihat angkanya.'
                            : months[picked].label + ' · masuk ' + months[picked].income + ' · keluar ' + months[picked].expense">
                        Ketuk satu bulan untuk melihat angkanya.
                    </p>
                </div>
            @endif
        </section>

        <section class="tape">
            <p class="tape__label">{{ $totals['net'] >= 0 ? 'Laba bersih' : 'Rugi bersih' }}</p>

            <p class="numeral {{ $totals['net'] >= 0 ? 'numeral--credit' : 'numeral--debit' }}">
                {{ Rupiah::format(abs($totals['net'])) }}
            </p>
            <p class="muted small" style="margin: 6px 0 0;">
                {{ $this->margin() === null ? 'Belum ada pendapatan bulan ini' : 'Margin '.$this->margin().'%' }}
            </p>
        </section>

        @foreach ([['Pengeluaran per kategori', 'expense', $totals['expense']], ['Pemasukan per kategori', 'income', $totals['income']]] as [$title, $type, $groupTotal])
            <section class="tape">
                <p class="tape__label">{{ $title }}</p>

                @forelse ($this->byCategory($type) as $row)
                    @php ($share = $this->shareOf($groupTotal, $row['total']))

                    <div class="entry entry--charted">
                        <div class="entry__label">
                            <p class="entry__title">{{ $row['category'] }}</p>
                            <p class="entry__meta">{{ $share }}% dari total</p>
                        </div>
                        <span class="entry__amount">{{ Rupiah::format($row['total']) }}</span>
                        <div class="gauge">
                            <span class="gauge__fill gauge__fill--{{ $type }}" style="width: {{ $share }}%;"></span>
                        </div>
                    </div>
                @empty
                    <div class="entry">
                        <div class="entry__label">
                            <p class="entry__title muted">Belum ada catatan</p>
                            <p class="entry__meta">Bulan ini masih kosong</p>
                        </div>
                        <span class="stamp stamp--muted">Kosong</span>
                    </div>
                @endforelse
            </section>
        @endforeach
    @endif

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
