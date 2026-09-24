<?php

use App\Services\LedgerWiper;
use App\Services\PendingChanges;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Livewire\Component;

new class extends Component
{
    public ?string $error = null;

    /**
     * Berpindah buku berarti mengganti seluruh isi salinan di ponsel, jadi catatan
     * yang belum terkirim harus diselamatkan dulu — kalau tidak, ia hilang tanpa jejak.
     */
    public function choose(string $publicId, SyncEngine $syncEngine, TokenStore $tokenStore, LedgerWiper $ledgerWiper, PendingChanges $pendingChanges): void
    {
        $this->error = null;

        if ($publicId === $tokenStore->bookPublicId()) {
            return;
        }

        $pendingCount = $pendingChanges->count();

        if ($pendingCount > 0) {
            $this->error = "Masih ada {$pendingCount} catatan yang belum terkirim. Sinkronkan dulu sebelum pindah buku.";

            return;
        }

        $book = collect($tokenStore->books())->firstWhere('public_id', $publicId);

        if ($book === null) {
            return;
        }

        $ledgerWiper->wipe();

        $tokenStore->forgetSync();
        $tokenStore->rememberBook($book['public_id'], $book['name']);

        try {
            $syncEngine->pull();
        } catch (\Throwable) {
            $this->error = 'Buku sudah diganti, tapi isinya belum bisa diambil. Coba sinkronkan lagi nanti.';
        }

        $this->redirect(route('home'));
    }

    /**
     * @return list<array{public_id: string, name: string, is_default: bool}>
     */
    public function books(): array
    {
        return app(TokenStore::class)->books();
    }

    public function activeBookPublicId(): ?string
    {
        return app(TokenStore::class)->bookPublicId();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Buku</p>
        <h1 class="masthead__title">Pilih buku<br>yang dibuka.</h1>
        <p class="masthead__note">Ponsel menyimpan satu buku pada satu waktu.</p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    <section class="tape">
        @forelse ($this->books() as $book)
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title">{{ $book['name'] }}</p>
                    @if ($book['public_id'] === $this->activeBookPublicId())
                        <p class="entry__meta">Sedang dibuka</p>
                    @endif
                </div>

                @if ($book['public_id'] === $this->activeBookPublicId())
                    <span class="stamp">Aktif</span>
                @else
                    <button class="linkish" type="button" wire:click="choose('{{ $book['public_id'] }}')"
                            wire:confirm="Ganti ke buku ini? Isi buku yang sekarang akan diganti.">Buka</button>
                @endif
            </div>
        @empty
            <p class="entry__title muted">Belum ada buku yang tercatat. Masuk ulang untuk memperbaruinya.</p>
        @endforelse
    </section>

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
