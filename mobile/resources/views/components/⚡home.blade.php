<?php

use App\Services\ApiClient;
use App\Services\TokenStore;
use Livewire\Component;

new class extends Component
{
    public string $userName = '';

    public string $bookName = '';

    public ?string $lastSyncedAt = null;

    public function mount(TokenStore $tokenStore): void
    {
        $this->userName = $tokenStore->userName() ?? '';
        $this->bookName = $tokenStore->bookName() ?? '';
        $this->lastSyncedAt = $tokenStore->lastSyncedAt();
    }

    public function signOut(ApiClient $apiClient, TokenStore $tokenStore): void
    {
        $apiClient->logout();
        $tokenStore->forget();

        $this->redirect(route('login'));
    }

    public function syncLabel(): string
    {
        return $this->lastSyncedAt === null
            ? 'Belum pernah'
            : \Carbon\CarbonImmutable::parse($this->lastSyncedAt)->diffForHumans();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">{{ $bookName }}</p>
        <h1 class="masthead__title">Halo, {{ $userName }}</h1>
        <p class="masthead__note">Sinkron terakhir: {{ $this->syncLabel() }}</p>
    </header>

    <section class="tape">
        <p class="tape__label">Saldo seluruh akun</p>
        <p class="numeral">Rp —</p>
        <p class="muted small" style="margin: 12px 0 0;">
            Angka muncul setelah tarikan pertama dari server.
        </p>
    </section>

    <section class="tape">
        <p class="tape__label">Catatan terakhir</p>

        <div class="entry">
            <div class="entry__label">
                <p class="entry__title muted">Belum ada transaksi tersalin</p>
                <p class="entry__meta">Pencatatan menyusul di tahap berikutnya</p>
            </div>
            <span class="stamp stamp--muted">Kosong</span>
        </div>
    </section>

    <button class="button button--quiet" type="button" wire:click="signOut">Keluar</button>
</div>
