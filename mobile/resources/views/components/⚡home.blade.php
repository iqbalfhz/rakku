<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\ApiClient;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use App\Support\Rupiah;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /**
     * Berapa banyak catatan terakhir yang ditampilkan di beranda.
     */
    private const int RECENT_LIMIT = 8;

    public string $userName = '';

    public string $bookName = '';

    public ?string $lastSyncedAt = null;

    public ?string $syncError = null;

    public function mount(TokenStore $tokenStore): void
    {
        $this->readSession($tokenStore);
    }

    /**
     * Tarik perubahan dari server. Kegagalan tidak menghapus apa pun di ponsel.
     */
    public function sync(SyncEngine $syncEngine, TokenStore $tokenStore): void
    {
        $this->syncError = null;

        try {
            $syncEngine->pull();
        } catch (\Throwable) {
            $this->syncError = 'Gagal menyambung ke server. Catatan di ponsel tetap aman.';

            return;
        }

        $this->readSession($tokenStore);
    }

    public function signOut(ApiClient $apiClient, TokenStore $tokenStore): void
    {
        $apiClient->logout();
        $tokenStore->forget();

        $this->redirect(route('login'));
    }

    public function balance(): string
    {
        return Rupiah::format((float) Account::query()->sum('current_balance'));
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function recentTransactions(): Collection
    {
        return Transaction::query()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    public function syncLabel(): string
    {
        return $this->lastSyncedAt === null
            ? 'Belum pernah'
            : \Carbon\CarbonImmutable::parse($this->lastSyncedAt)->diffForHumans();
    }

    private function readSession(TokenStore $tokenStore): void
    {
        $this->userName = $tokenStore->userName() ?? '';
        $this->bookName = $tokenStore->bookName() ?? '';
        $this->lastSyncedAt = $tokenStore->lastSyncedAt();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">{{ $bookName }}</p>
        <h1 class="masthead__title">Halo, {{ $userName }}</h1>
        <p class="masthead__note">Sinkron terakhir: {{ $this->syncLabel() }}</p>
    </header>

    @if ($syncError)
        <p class="notice">{{ $syncError }}</p>
    @endif

    <section class="tape">
        <p class="tape__label">Saldo seluruh akun</p>
        <p class="numeral">{{ $this->balance() }}</p>

        <button class="button" type="button" wire:click="sync" wire:loading.attr="disabled" style="margin-top: 20px;">
            <span wire:loading.remove wire:target="sync">Sinkronkan sekarang</span>
            <span wire:loading wire:target="sync">Menarik data…</span>
        </button>
    </section>

    <section class="tape">
        <p class="tape__label">Catatan terakhir</p>

        @forelse ($this->recentTransactions() as $transaction)
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title">{{ $transaction->description ?: 'Tanpa keterangan' }}</p>
                    <p class="entry__meta">
                        {{ $transaction->transaction_date->translatedFormat('j M Y') }}
                        @if ($transaction->account) · {{ $transaction->account->name }} @endif
                    </p>
                </div>
                <span class="entry__amount {{ $transaction->isIncome() ? 'numeral--credit' : 'numeral--debit' }}">
                    {{ $transaction->isIncome() ? '+' : '−' }}{{ Rupiah::format((float) $transaction->amount) }}
                </span>
            </div>
        @empty
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title muted">Belum ada catatan tersalin</p>
                    <p class="entry__meta">Tekan Sinkronkan untuk menariknya dari server</p>
                </div>
                <span class="stamp stamp--muted">Kosong</span>
            </div>
        @endforelse
    </section>

    <button class="button button--quiet" type="button" wire:click="signOut">Keluar</button>
</div>
