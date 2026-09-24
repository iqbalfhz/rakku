<?php

use App\Services\LedgerReport;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
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
            <p class="muted small" style="margin: 10px 0 0;">
                Aktifkan lewat rakku.iqbalfhz.my.id, lalu sinkronkan dari Beranda supaya ponsel ikut tahu.
            </p>
        </section>
    @else
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
                    <div class="entry">
                        <div class="entry__label">
                            <p class="entry__title">{{ $row['category'] }}</p>
                            <p class="entry__meta">{{ $this->shareOf($groupTotal, $row['total']) }}% dari total</p>
                        </div>
                        <span class="entry__amount">{{ Rupiah::format($row['total']) }}</span>
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
