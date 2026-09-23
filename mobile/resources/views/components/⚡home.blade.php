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
};

?>

<div>
    <h1>Halo, {{ $userName }}</h1>
    <p class="lead">Buku aktif: {{ $bookName }}</p>

    <div class="card">
        <h2>Belum ada data</h2>
        <p class="muted">
            Pencatatan transaksi dan saldo menyusul di tahap berikutnya.
            Sekarang aplikasi ini baru bisa masuk dan mengingat buku Anda.
        </p>
    </div>

    <button class="ghost" type="button" wire:click="signOut">Keluar</button>
</div>
