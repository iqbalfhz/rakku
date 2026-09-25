<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Services\ApiClient;
use App\Services\AutoSync;
use App\Services\LedgerWiper;
use App\Services\LedgerWriter;
use App\Services\PendingChanges;
use App\Services\PlanGate;
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
     * Kirim catatan yang tertunda, lalu tarik yang baru.
     * Kegagalan tidak menghapus apa pun di ponsel.
     */
    public function sync(SyncEngine $syncEngine, TokenStore $tokenStore): void
    {
        $this->syncError = null;

        try {
            $syncEngine->sync();
        } catch (\Throwable) {
            $this->syncError = 'Gagal menyambung ke server. Catatan di ponsel tetap aman dan akan dikirim saat sinyal kembali.';

            return;
        }

        $this->readSession($tokenStore);
    }

    /**
     * Sinkron yang berjalan sendiri: saat beranda terbuka, saat aplikasi kembali
     * ke depan, dan berkala selama dibiarkan terbuka. Gagal tidak diberitahukan —
     * pengguna tidak sedang meminta apa pun, dan tombol manual tetap ada
     * untuk saat mereka ingin memastikan.
     */
    public function autoSync(AutoSync $autoSync, TokenStore $tokenStore): void
    {
        if ($autoSync->attempt()) {
            $this->readSession($tokenStore);
        }
    }

    public function remove(int $transactionId, LedgerWriter $ledgerWriter): void
    {
        $transaction = Transaction::query()->visible()->find($transactionId);

        if ($transaction !== null) {
            $ledgerWriter->remove($transaction);
        }
    }

    /**
     * Keluar berarti ponsel ini dibersihkan: isi buku orang lain tidak boleh
     * tertinggal untuk siapa pun yang masuk berikutnya.
     */
    public function signOut(ApiClient $apiClient, TokenStore $tokenStore, PlanGate $planGate, LedgerWiper $ledgerWiper, PendingChanges $pendingChanges): void
    {
        $pendingCount = $pendingChanges->count();

        if ($pendingCount > 0) {
            $this->syncError = "Masih ada {$pendingCount} catatan yang belum terkirim. Sinkronkan dulu sebelum keluar.";

            return;
        }

        $apiClient->logout();
        $ledgerWiper->wipe();
        $tokenStore->forget();
        $planGate->forget();

        $this->redirect(route('login'));
    }

    public function balance(): string
    {
        return Rupiah::format((float) Account::query()->visible()->sum('current_balance'));
    }

    public function pendingCount(): int
    {
        return app(PendingChanges::class)->count();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function recentTransactions(): Collection
    {
        return Transaction::query()
            ->visible()
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * Yang tersimpan adalah jam server — itu penanda posisi sinkron, jadi tidak
     * boleh diganti jam ponsel. Tapi labelnya dibandingkan dengan jam ponsel,
     * dan selisih beberapa detik saja sudah membuatnya terbaca sebagai masa
     * depan: "36 detik dari sekarang".
     */
    public function syncLabel(): string
    {
        if ($this->lastSyncedAt === null) {
            return 'Belum pernah';
        }

        $syncedAt = \Carbon\CarbonImmutable::parse($this->lastSyncedAt);

        return $syncedAt->isFuture() ? 'Baru saja' : $syncedAt->diffForHumans();
    }

    private function readSession(TokenStore $tokenStore): void
    {
        $this->userName = $tokenStore->userName() ?? '';
        $this->bookName = $tokenStore->bookName() ?? '';
        $this->lastSyncedAt = $tokenStore->lastSyncedAt();
    }
};

?>

<div wire:poll.60s="autoSync"
     x-on:bridge-ready.window="$wire.autoSync()"
     x-on:visibilitychange.document="if (! document.hidden) { $wire.autoSync() }"
     x-on:online.window="$wire.autoSync()">
    <header class="masthead">
        <p class="masthead__brand"><a href="{{ route('books') }}" wire:navigate style="color: inherit;">{{ $bookName }} · ganti</a></p>
        <h1 class="masthead__title">Halo, {{ $userName }}</h1>
        <p class="masthead__note">Sinkron terakhir: {{ $this->syncLabel() }}</p>
    </header>

    @if ($syncError)
        <p class="notice">{{ $syncError }}</p>
    @endif

    <section class="tape">
        <p class="tape__label">Saldo seluruh akun</p>
        <p class="numeral">{{ $this->balance() }}</p>

        @if ($this->pendingCount() > 0)
            <p class="muted small" style="margin: 12px 0 0;">
                {{ $this->pendingCount() }} catatan menunggu dikirim. Terkirim sendiri saat ada sinyal.
            </p>
        @endif

        <a class="button" href="{{ route('record') }}" wire:navigate style="margin-top: 20px;">Catat transaksi</a>

        <button class="button button--quiet" type="button" wire:click="sync" wire:loading.attr="disabled" style="margin-top: 10px;">
            <span wire:loading.remove wire:target="sync">Sinkronkan sekarang</span>
            <span wire:loading wire:target="sync">Menyinkronkan…</span>
        </button>

    </section>

    <section class="tape">
        <p class="tape__label">Buka juga</p>

        @foreach ([['transfers', 'Pindah uang', 'Antar akun sendiri, tanpa mengotori laporan'], ['setup', 'Akun & kategori', 'Tempat uang disimpan dan cara mengelompokkannya'], ['report', 'Laporan', 'Arus kas dan laba-rugi per bulan'], ['budgets', 'Anggaran', 'Jatah belanja per kategori'], ['invoices', 'Invoice', 'Tagihan untuk klien'], ['recurring', 'Transaksi berulang', 'Sewa, listrik, dan yang tiap bulan sama'], ['account', 'Akun saya', 'Langganan, buku, dan hapus akun']] as [$route, $title, $note])
            <a class="entry" href="{{ route($route) }}" wire:navigate style="color: inherit; text-decoration: none;">
                <div class="entry__label">
                    <p class="entry__title">{{ $title }}</p>
                    <p class="entry__meta">{{ $note }}</p>
                </div>
                <span class="entry__amount muted">›</span>
            </a>
        @endforeach
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
        @empty
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title muted">Belum ada catatan</p>
                    <p class="entry__meta">Tekan Catat transaksi untuk menulis yang pertama</p>
                </div>
                <span class="stamp stamp--muted">Kosong</span>
            </div>
        @endforelse
    </section>

    <button class="button button--quiet" type="button" wire:click="signOut">Keluar</button>
</div>
