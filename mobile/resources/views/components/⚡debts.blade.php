<?php

use App\Models\Debt;
use App\Services\DebtReminderScheduler;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public bool $showPaid = false;

    /**
     * Izin notifikasi diminta di sini, saat pengguna memang sedang mengurus utang,
     * bukan saat aplikasi baru dibuka dan permintaannya belum jelas untuk apa.
     */
    public function mount(DebtReminderScheduler $reminderScheduler): void
    {
        if ($this->isPremium()) {
            $reminderScheduler->requestPermission();
        }
    }

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    /**
     * @return Collection<int, Debt>
     */
    public function receivables(): Collection
    {
        return $this->debtsOfType('receivable');
    }

    /**
     * @return Collection<int, Debt>
     */
    public function payables(): Collection
    {
        return $this->debtsOfType('payable');
    }

    public function totalReceivable(): string
    {
        return Rupiah::format((float) Debt::query()->visible()->unpaid()->where('type', 'receivable')->sum('remaining_amount'));
    }

    public function totalPayable(): string
    {
        return Rupiah::format((float) Debt::query()->visible()->unpaid()->where('type', 'payable')->sum('remaining_amount'));
    }

    /**
     * @return Collection<int, Debt>
     */
    private function debtsOfType(string $type): Collection
    {
        return Debt::query()
            ->visible()
            ->where('type', $type)
            ->when(! $this->showPaid, fn ($query) => $query->unpaid())
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('counterparty_name')
            ->get();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Utang-piutang</p>
        <h1 class="masthead__title">Siapa nunggak,<br>siapa ditagih.</h1>
        <p class="masthead__note">Tercatat di ponsel, ikut tersinkron saat ada sinyal.</p>
    </header>

    @if (! $this->isPremium())
        <section class="tape">
            <p class="tape__label">Fitur premium</p>
            <p class="entry__title" style="margin: 8px 0 0;">Utang-piutang terbuka setelah langganan aktif.</p>
            <p class="muted small" style="margin: 10px 0 0;">
                Aktifkan lewat rakku.iqbalfhz.my.id, lalu sinkronkan dari Beranda supaya ponsel ikut tahu.
            </p>
        </section>

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 16px;">Kembali</a>
    @else
        <section class="tape">
            <div class="tape__header">
                <p class="tape__label" style="margin: 0;">Ringkasan</p>
                <label class="linkish" style="cursor: pointer;">
                    <input type="checkbox" wire:model.live="showPaid" style="margin-right: 6px;">Tampilkan yang lunas
                </label>
            </div>

            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title">Piutang berjalan</p>
                    <p class="entry__meta">Uang yang masih harus ditagih</p>
                </div>
                <span class="entry__amount numeral--credit">{{ $this->totalReceivable() }}</span>
            </div>

            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title">Utang berjalan</p>
                    <p class="entry__meta">Uang yang masih harus dibayar</p>
                </div>
                <span class="entry__amount numeral--debit">{{ $this->totalPayable() }}</span>
            </div>

            <a class="button" href="{{ route('debt.create') }}" wire:navigate style="margin-top: 20px;">Catat utang baru</a>
        </section>

        @foreach ([['Piutang', $this->receivables()], ['Utang', $this->payables()]] as [$title, $debts])
            <section class="tape">
                <p class="tape__label">{{ $title }}</p>

                @forelse ($debts as $debt)
                    <a class="entry" href="{{ route('debt.show', $debt->public_id) }}" wire:navigate
                       style="color: inherit; text-decoration: none;">
                        <div class="entry__label">
                            <p class="entry__title">{{ $debt->counterparty_name }}</p>
                            <p class="entry__meta">
                                @if ($debt->due_date)
                                    Jatuh tempo {{ $debt->due_date->translatedFormat('j M Y') }}
                                @else
                                    Tanpa jatuh tempo
                                @endif
                                @if ($debt->isOverdue()) · <span class="stamp stamp--due">Lewat</span> @endif
                                @if ($debt->isPaid()) · <span class="stamp stamp--muted">Lunas</span> @endif
                                @if ($debt->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                            </p>
                        </div>

                        <span class="entry__amount">{{ Rupiah::format((float) $debt->remaining_amount) }}</span>
                    </a>
                @empty
                    <div class="entry">
                        <div class="entry__label">
                            <p class="entry__title muted">Belum ada catatan</p>
                            <p class="entry__meta">Tekan Catat utang baru untuk menulis yang pertama</p>
                        </div>
                        <span class="stamp stamp--muted">Kosong</span>
                    </div>
                @endforelse
            </section>
        @endforeach
    @endif
</div>
