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

    /**
     * Pintasan di beranda, beserta gambar garisnya.
     *
     * Ikonnya ditulis sebagai jalur SVG di sini, bukan diambil dari pustaka ikon:
     * aplikasi ini tidak punya proses build aset, dan satu paket ikon berarti
     * ratusan gambar ikut dibungkus ke APK demi tujuh yang dipakai. Semua digambar
     * dengan garis setipis garis rambut di sisa aplikasi, tanpa isian, dan mewarisi
     * warna dari induknya supaya ikut berganti sendiri di mode gelap.
     *
     * @return list<array{route: string, title: string, paths: list<string>}>
     */
    public function shortcuts(): array
    {
        return [
            ['route' => 'transfers', 'title' => 'Pindah uang', 'paths' => [
                'M3 9h14', 'm13 5 4 4-4 4', 'M21 15H7', 'm11 19-4-4 4-4',
            ]],
            ['route' => 'setup', 'title' => 'Akun & kategori', 'paths' => [
                'M4 4h6v6H4z', 'M14 4h6v6h-6z', 'M4 14h6v6H4z', 'M14 14h6v6h-6z',
            ]],
            ['route' => 'report', 'title' => 'Laporan', 'paths' => [
                'M3 21h18', 'M6 21V11', 'M12 21V4', 'M18 21v-6',
            ]],
            ['route' => 'budgets', 'title' => 'Anggaran', 'paths' => [
                'M4 18a8 8 0 0 1 16 0', 'M12 18l4.5-4.5',
            ]],
            ['route' => 'invoices', 'title' => 'Invoice', 'paths' => [
                'M6 3h9l4 4v14H6z', 'M15 3v4h4', 'M9 12h7', 'M9 16h5',
            ]],
            ['route' => 'recurring', 'title' => 'Transaksi berulang', 'paths' => [
                'M20 12a8 8 0 1 1-2.4-5.7', 'M20 4v4h-4',
            ]],
            ['route' => 'account', 'title' => 'Akun saya', 'paths' => [
                'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M5 20a7 7 0 0 1 14 0',
            ]],
        ];
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

    /**
     * Sinkron sudah berjalan sendiri — saat halaman siap, saat aplikasi kembali ke
     * depan, saat sinyal pulih, dan tiap menit selagi terbuka. Jadi tombol manual
     * tidak dibutuhkan agar sinkron terjadi, dan menampilkannya terus-menerus hanya
     * memenuhi layar dengan tawaran yang tidak perlu dijawab.
     *
     * Yang tetap hanya bisa dilakukan tombol itu adalah **memberi tahu kalau gagal**:
     * sinkron otomatis sengaja pendiam supaya tidak mengganggu, jadi token yang
     * dicabut atau server yang bermasalah tidak akan pernah terdengar tanpanya.
     * Karena itu ia muncul justru saat ada yang perlu dikhawatirkan.
     */
    public function needsManualSync(TokenStore $tokenStore): bool
    {
        return $this->syncError !== null
            || $this->pendingCount() > 0
            || $tokenStore->lastSyncAttemptFailed();
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
    <header class="masthead masthead--compact">
        <p class="masthead__brand"><a href="{{ route('books') }}" wire:navigate style="color: inherit;">{{ $bookName }} · ganti</a></p>
        <h1 class="masthead__title">Halo, {{ $userName }}</h1>
        <p class="masthead__note">Sinkron terakhir: {{ $this->syncLabel() }}</p>
    </header>

    @if ($syncError)
        <p class="notice">{{ $syncError }}</p>
    @endif

    {{--
        Buku kas sering dibuka di depan orang lain — di warung, di meja pelanggan.
        Pilihannya disimpan di perangkat ini saja, bukan ikut tersinkron: yang ingin
        disembunyikan adalah layar ini, bukan saldo di perangkat lain.

        Keduanya diberi x-cloak supaya sebelum Alpine menyala tidak ada yang tampil
        sama sekali. Tanpa itu, saldo sempat berkedip terlihat lebih dulu — persis
        yang sedang dihindari.
    --}}
    <section class="tape" x-data="{
        hidden: false,
        init() {
            try { this.hidden = localStorage.getItem('rakku:saldo-disembunyikan') === '1' } catch (e) {}
        },
        toggle() {
            this.hidden = ! this.hidden
            try { localStorage.setItem('rakku:saldo-disembunyikan', this.hidden ? '1' : '0') } catch (e) {}
        },
    }">
        <p class="tape__label tape__label--with-action">
            Saldo seluruh akun

            <button type="button" class="peek" x-on:click="toggle()"
                    x-bind:aria-label="hidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6z"></path>
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6"></path>
                    <path d="M4 4l16 16" x-show="hidden" x-cloak></path>
                </svg>
            </button>
        </p>

        <p class="numeral" x-show="! hidden" x-cloak>{{ $this->balance() }}</p>
        <p class="numeral numeral--hidden" x-show="hidden" x-cloak>Rp ••••••</p>

        @if ($this->pendingCount() > 0)
            <p class="muted small" style="margin: 12px 0 0;">
                {{ $this->pendingCount() }} catatan menunggu dikirim. Terkirim sendiri saat ada sinyal.
            </p>
        @endif

        <a class="button" href="{{ route('record') }}" wire:navigate style="margin-top: 20px;">Catat transaksi</a>

        @if ($this->needsManualSync(app(TokenStore::class)))
            <button class="button button--quiet" type="button" wire:click="sync" wire:loading.attr="disabled" style="margin-top: 10px;">
                <span wire:loading.remove wire:target="sync">Sinkronkan sekarang</span>
                <span wire:loading wire:target="sync">Menyinkronkan…</span>
            </button>
        @endif

    </section>

    {{--
        Dulu tujuh baris berisi judul dan keterangan panjang, dan tujuh baris itu
        mendorong "Catatan terakhir" — yang paling sering dibaca — jauh ke bawah
        lipatan. Sebagai petak, ketujuhnya muat dalam dua baris.
    --}}
    <section class="tape">
        <p class="tape__label">Buka juga</p>

        <nav class="grid">
            @foreach ($this->shortcuts() as $shortcut)
                <a class="grid__item" href="{{ route($shortcut['route']) }}" wire:navigate>
                    <svg class="grid__icon" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        @foreach ($shortcut['paths'] as $d)
                            <path d="{{ $d }}"></path>
                        @endforeach
                    </svg>
                    <span class="grid__label">{{ $shortcut['title'] }}</span>
                </a>
            @endforeach
        </nav>
    </section>

    <section class="tape">
        <p class="tape__label">Catatan terakhir</p>

        @forelse ($this->recentTransactions() as $transaction)
            <div class="entry entry--tappable">
                <a class="entry__hit" href="{{ route('record', $transaction->public_id) }}" wire:navigate
                   aria-label="Ubah {{ $transaction->description ?: 'catatan tanpa keterangan' }}"></a>

                <div class="entry__label">
                    <p class="entry__title">{{ $transaction->description ?: 'Tanpa keterangan' }}</p>
                    <p class="entry__meta">
                        {{ $transaction->transaction_date->translatedFormat('j M Y') }}
                        @if ($transaction->account) · {{ $transaction->account->name }} @endif
                        @if ($transaction->is_dirty) · <span class="stamp">Belum terkirim</span> @endif
                    </p>
                </div>

                <div class="entry__trailing">
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
