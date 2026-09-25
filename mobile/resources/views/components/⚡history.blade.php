<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\LedgerWriter;
use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * Berapa banyak catatan yang ditarik sekaligus. Buku yang sudah berjalan setahun
     * bisa punya ribuan baris, dan ponsel tidak perlu menggambar semuanya sekaligus.
     */
    public const int PAGE_SIZE = 50;

    public string $filter = 'all';

    public string $search = '';

    public string $month = '';

    public string $accountPublicId = '';

    public string $categoryPublicId = '';

    public bool $isFiltering = false;

    public int $limit = self::PAGE_SIZE;

    public function remove(int $transactionId, LedgerWriter $ledgerWriter): void
    {
        $transaction = Transaction::query()->visible()->find($transactionId);

        if ($transaction !== null) {
            $ledgerWriter->remove($transaction);
        }
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function clearFilters(): void
    {
        $this->filter = 'all';
        $this->search = '';
        $this->month = '';
        $this->accountPublicId = '';
        $this->categoryPublicId = '';
        $this->limit = self::PAGE_SIZE;
    }

    /**
     * Setiap penyaringan mengubah isi daftar, jadi hitungannya dimulai lagi dari atas.
     */
    public function updated(string $property): void
    {
        if ($property !== 'limit') {
            $this->limit = self::PAGE_SIZE;
        }
    }

    public function hasActiveFilters(): bool
    {
        return $this->filter !== 'all'
            || $this->search !== ''
            || $this->month !== ''
            || $this->accountPublicId !== ''
            || $this->categoryPublicId !== '';
    }

    public function matchCount(): int
    {
        return $this->matches()->count();
    }

    public function hasMore(): bool
    {
        return $this->matchCount() > $this->limit;
    }

    /**
     * Catatan dikelompokkan per tanggal supaya terbaca seperti halaman buku kas.
     *
     * @return Collection<string, Collection<int, Transaction>>
     */
    public function days(): Collection
    {
        return $this->matches()
            ->with(['account', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get()
            ->groupBy(fn (Transaction $transaction): string => $transaction->transaction_date->toDateString());
    }

    /**
     * Jumlah bersih dari seluruh yang cocok, bukan hanya yang sedang tampil —
     * angka yang berubah saat menggulir bukan angka yang bisa dipercaya.
     */
    public function matchTotal(): string
    {
        $totals = $this->matches()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $net = (float) ($totals['income'] ?? 0) - (float) ($totals['expense'] ?? 0);

        return ($net < 0 ? '−' : '').Rupiah::format(abs($net));
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    public function dayTotal(Collection $transactions): string
    {
        return Rupiah::format($transactions->sum(fn (Transaction $transaction): float => $transaction->signedAmount()));
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()->visible()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->visible()->orderBy('name')->get();
    }

    /**
     * @return Builder<Transaction>
     */
    private function matches(): Builder
    {
        return Transaction::query()
            ->visible()
            ->when($this->filter !== 'all', fn (Builder $query) => $query->where('type', $this->filter))
            ->when($this->accountPublicId !== '', fn (Builder $query) => $query->where('account_public_id', $this->accountPublicId))
            ->when($this->categoryPublicId !== '', fn (Builder $query) => $query->where('category_public_id', $this->categoryPublicId))
            ->when($this->month !== '', fn (Builder $query) => $query->whereBetween('transaction_date', [
                $this->month.'-01',
                \Carbon\CarbonImmutable::parse($this->month.'-01')->endOfMonth()->toDateString(),
            ]))
            ->when($this->search !== '', fn (Builder $query) => $query->where('description', 'like', '%'.$this->search.'%'));
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Riwayat</p>
        <h1 class="masthead__title">Semua<br>catatan.</h1>
        @if ($this->hasActiveFilters())
            <p class="masthead__note">{{ $this->matchCount() }} catatan cocok · {{ $this->matchTotal() }}</p>
        @endif
    </header>

    <div class="field">
        <label class="field__label" for="search">Cari keterangan</label>
        <input class="field__input" id="search" type="search" placeholder="Beli kertas"
               wire:model.live.debounce.400ms="search">
    </div>

    <div class="choice" style="margin-bottom: 16px;">
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

    @unless ($isFiltering)
        <button class="linkish" type="button" wire:click="$set('isFiltering', true)" style="margin-bottom: 22px;">
            Saring lebih jauh
        </button>
    @else
        <section class="tape">
            <p class="tape__label">Saringan</p>

            <div class="field">
                <label class="field__label" for="history-month">Bulan</label>
                <input class="field__input" id="history-month" type="month" wire:model.live="month">
            </div>

            <div class="field">
                <label class="field__label" for="history-account">Akun</label>
                <select class="field__input" id="history-account" wire:model.live="accountPublicId">
                    <option value="">Semua akun</option>
                    @foreach ($this->accounts() as $account)
                        <option value="{{ $account->public_id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="field__label" for="history-category">Kategori</label>
                <select class="field__input" id="history-category" wire:model.live="categoryPublicId">
                    <option value="">Semua kategori</option>
                    @foreach ($this->categories() as $category)
                        <option value="{{ $category->public_id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <button class="button button--quiet" type="button" wire:click="clearFilters">Bersihkan saringan</button>
            <button class="linkish" type="button" wire:click="$set('isFiltering', false)" style="margin-top: 12px;">Tutup</button>
        </section>
    @endunless

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
                        <a class="linkish" href="{{ route('record', $transaction->public_id) }}" wire:navigate>Ubah</a>
                        <button class="linkish" type="button" wire:click="remove({{ $transaction->id }})"
                                wire:confirm="Hapus catatan ini?">Hapus</button>
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="tape">
            <p class="entry__title muted">
                {{ $this->hasActiveFilters() ? 'Tidak ada yang cocok' : 'Belum ada catatan di sini' }}
            </p>
            <p class="entry__meta">
                {{ $this->hasActiveFilters()
                    ? 'Coba longgarkan saringannya.'
                    : 'Catatan yang Anda tulis akan berbaris di halaman ini.' }}
            </p>
        </section>
    @endforelse

    @if ($this->hasMore())
        <button class="button button--quiet" type="button" wire:click="showMore">Tampilkan lebih banyak</button>
    @endif
</div>
