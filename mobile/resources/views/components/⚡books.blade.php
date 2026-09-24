<?php

use App\Services\ApiClient;
use App\Services\LedgerWiper;
use App\Services\PendingChanges;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Livewire\Component;

new class extends Component
{
    public ?string $error = null;

    public bool $isAdding = false;

    public string $newBookName = '';

    public function startAdding(): void
    {
        $this->error = null;
        $this->isAdding = true;
    }

    public function cancelAdding(): void
    {
        $this->resetValidation();

        $this->isAdding = false;
        $this->newBookName = '';
    }

    /**
     * Buku baru dibuat di server dulu, baru dikenal ponsel. Kalau ponsel belum
     * memegang buku apa pun, buku ini langsung dibuka — itu satu-satunya yang ada.
     */
    public function create(ApiClient $apiClient, TokenStore $tokenStore, SyncEngine $syncEngine): void
    {
        $data = $this->validate([
            'newBookName' => ['required', 'string', 'max:255'],
        ], attributes: ['newBookName' => 'nama buku']);

        $this->error = null;

        try {
            $created = $apiClient->createBook($data['newBookName']);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $tokenStore->rememberBooks($created['books']);

        $this->isAdding = false;
        $this->newBookName = '';

        if ($tokenStore->bookPublicId() !== null) {
            return;
        }

        $tokenStore->rememberBook($created['book']['public_id'], $created['book']['name']);

        try {
            $syncEngine->pull();
        } catch (\Throwable) {
            $this->error = 'Buku sudah dibuat, tapi isinya belum bisa diambil. Coba sinkronkan lagi nanti.';

            return;
        }

        $this->redirect(route('home'));
    }

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
            <div class="entry">
                <div class="entry__label">
                    <p class="entry__title muted">Belum ada buku</p>
                    <p class="entry__meta">Buat satu untuk mulai mencatat</p>
                </div>
                <span class="stamp stamp--muted">Kosong</span>
            </div>
        @endforelse

        @unless ($isAdding)
            <button class="button" type="button" wire:click="startAdding" style="margin-top: 20px;">Buku baru</button>
        @endunless
    </section>

    @if ($isAdding)
        <section class="tape">
            <p class="tape__label">Buku baru</p>

            <form wire:submit="create">
                <div class="field">
                    <label class="field__label" for="book-name">Nama buku</label>
                    <input class="field__input" id="book-name" type="text" placeholder="Warung Kopi"
                           wire:model="newBookName">
                    @error('newBookName') <p class="field__hint">{{ $message }}</p> @enderror
                    <p class="field__hint">Perlu sinyal: buku baru dibuat di server.</p>
                </div>

                <button class="button" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="create">Buat buku</span>
                    <span wire:loading wire:target="create">Membuat…</span>
                </button>
            </form>

            <button class="button button--quiet" type="button" wire:click="cancelAdding" style="margin-top: 10px;">Batal</button>
        </section>
    @endif

    @if ($this->activeBookPublicId() !== null)
        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
    @endif
</div>
