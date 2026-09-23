<?php

use App\Models\Transaction;
use App\Services\LedgerWriter;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $filter = 'all';

    public function remove(int $transactionId, LedgerWriter $ledgerWriter): void
    {
        $transaction = Transaction::query()->visible()->find($transactionId);

        if ($transaction !== null) {
            $ledgerWriter->remove($transaction);
        }
    }

    /**
     * Catatan dikelompokkan per tanggal supaya terbaca seperti halaman buku kas.
     *
     * @return Collection<string, Collection<int, Transaction>>
     */
    public function days(): Collection
    {
        return Transaction::query()
            ->visible()
            ->when($this->filter !== 'all', fn ($query) => $query->where('type', $this->filter))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Transaction $transaction): string => $transaction->transaction_date->toDateString());
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    public function dayTotal(Collection $transactions): string
    {
        return Rupiah::format($transactions->sum(fn (Transaction $transaction): float => $transaction->signedAmount()));
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Riwayat</p>
        <h1 class="masthead__title">Semua<br>catatan.</h1>
    </header>

    <div class="choice" style="margin-bottom: 22px;">
        <label class="choice__option {{ $filter === 'all' ? 'choice__option--on' : '' }}">
            <input type="radio" value="all" wire:model.live="filter"> Semua
        </label>
        <label class="choice__option {{ $filter === 'income' ? 'choice__option--on' : '' }}">
            <input type="radio" value="income" wire:model.live="filter"> Masuk
        </label>
        <label class="choice__option {{ $filter === 'expense' ? 'choice__option--on' : '' }}">
            <input type="radio" value="expense" wire:model.live="filter"> Keluar
        </label>
    </div>

    @forelse ($this->days() as $date => $transactions)
        <section class="tape">
            <div class="tape__header">
                <p class="tape__label" style="margin: 0;">
                    {{ \Carbon\CarbonImmutable::parse($date)->translatedFormat('l, j F Y') }}
                </p>
                <p class="tape__total">{{ $this->dayTotal($transactions) }}</p>
            </div>

            @foreach ($transactions as $transaction)
                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title">{{ $transaction->description ?: 'Tanpa keterangan' }}</p>
                        <p class="entry__meta">
                            @if ($transaction->account) {{ $transaction->account->name }} @endif
                            @if ($transaction->category) · {{ $transaction->category->name }} @endif
                            @if ($transaction->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                        </p>
                    </div>

                    <div style="display: flex; align-items: baseline; gap: 12px; flex-shrink: 0;">
                        <span class="entry__amount {{ $transaction->isIncome() ? 'numeral--credit' : 'numeral--debit' }}">
                            {{ $transaction->isIncome() ? '+' : '−' }}{{ Rupiah::format((float) $transaction->amount) }}
                        </span>
                        <button class="linkish" type="button" wire:click="remove({{ $transaction->id }})"
                                wire:confirm="Hapus catatan ini?">Hapus</button>
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="tape">
            <p class="entry__title muted">Belum ada catatan di sini</p>
            <p class="entry__meta">Catatan yang Anda tulis akan berbaris di halaman ini.</p>
        </section>
    @endforelse
</div>
