<?php

use App\Models\Invoice;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $filter = 'open';

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function invoices(): Collection
    {
        return Invoice::query()
            ->visible()
            ->with('client')
            ->when($this->filter === 'open', fn ($query) => $query->whereNot('status', 'paid'))
            ->when($this->filter === 'paid', fn ($query) => $query->where('status', 'paid'))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    public function totalOutstanding(): string
    {
        return Rupiah::format((float) Invoice::query()->visible()->whereNot('status', 'paid')->sum('total_amount'));
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Invoice</p>
        <h1 class="masthead__title">Tagihan yang<br>belum kembali.</h1>
        <p class="masthead__note">Buat, kirim, dan lunasi dari mana saja.</p>
    </header>

    @if (! $this->isPremium())
        <section class="tape">
            <p class="tape__label">Fitur premium</p>
            <p class="entry__title" style="margin: 8px 0 0;">Invoice terbuka setelah langganan aktif.</p>
            <p class="muted small" style="margin: 10px 0 0;">
                Aktifkan lewat rakku.iqbalfhz.my.id, lalu sinkronkan dari Beranda supaya ponsel ikut tahu.
            </p>
        </section>

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 16px;">Kembali</a>
    @else
        <section class="tape">
            <p class="tape__label">Belum tertagih</p>
            <p class="numeral">{{ $this->totalOutstanding() }}</p>

            <a class="button" href="{{ route('invoice.create') }}" wire:navigate style="margin-top: 20px;">Buat invoice</a>
        </section>

        <div class="field">
            <div class="choice">
                <label class="choice__option {{ $filter === 'open' ? 'choice__option--on' : '' }}">
                    <input type="radio" value="open" wire:model.live="filter"> Berjalan
                </label>
                <label class="choice__option {{ $filter === 'paid' ? 'choice__option--on' : '' }}">
                    <input type="radio" value="paid" wire:model.live="filter"> Lunas
                </label>
                <label class="choice__option {{ $filter === 'all' ? 'choice__option--on' : '' }}">
                    <input type="radio" value="all" wire:model.live="filter"> Semua
                </label>
            </div>
        </div>

        <section class="tape">
            <p class="tape__label">Daftar invoice</p>

            @forelse ($this->invoices() as $invoice)
                <a class="entry" href="{{ route('invoice.show', $invoice->public_id) }}" wire:navigate
                   style="color: inherit; text-decoration: none;">
                    <div class="entry__label">
                        <p class="entry__title">{{ $invoice->client->name ?? 'Klien terhapus' }}</p>
                        <p class="entry__meta">
                            {{ $invoice->numberLabel() }} · jatuh tempo {{ $invoice->due_date->translatedFormat('j M Y') }}
                            @if ($invoice->isOverdue()) · <span class="stamp stamp--due">Telat</span> @endif
                            @if ($invoice->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                        </p>
                    </div>

                    <div style="text-align: right; flex-shrink: 0;">
                        <span class="entry__amount">{{ Rupiah::format((float) $invoice->total_amount) }}</span>
                        <p class="entry__meta" style="margin: 2px 0 0;">{{ $invoice->statusLabel() }}</p>
                    </div>
                </a>
            @empty
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title muted">Belum ada invoice</p>
                        <p class="entry__meta">Tekan Buat invoice untuk menulis yang pertama</p>
                    </div>
                    <span class="stamp stamp--muted">Kosong</span>
                </div>
            @endforelse
        </section>
    @endif
</div>
