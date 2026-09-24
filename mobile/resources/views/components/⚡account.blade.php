<?php

use App\Services\ApiClient;
use App\Services\LedgerWiper;
use App\Services\PendingChanges;
use App\Services\PlanGate;
use App\Services\TokenStore;
use Livewire\Component;

new class extends Component
{
    public bool $isDeleting = false;

    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    public function startDeleting(): void
    {
        $this->error = null;
        $this->isDeleting = true;
    }

    public function cancelDeleting(): void
    {
        $this->resetValidation();

        $this->isDeleting = false;
        $this->email = '';
        $this->password = '';
    }

    /**
     * Hapus akun. Pagarnya sama dengan di web — ketik ulang email dan kata sandi —
     * karena yang hilang di sini tidak bisa dikembalikan oleh siapa pun.
     */
    public function deleteAccount(
        ApiClient $apiClient,
        TokenStore $tokenStore,
        LedgerWiper $ledgerWiper,
        PlanGate $planGate,
    ): void {
        $data = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], attributes: ['email' => 'email', 'password' => 'kata sandi']);

        $this->error = null;

        try {
            $apiClient->deleteAccount($data['email'], $data['password']);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $ledgerWiper->wipe();
        $tokenStore->forget();
        $planGate->forget();

        $this->redirect(route('login'));
    }

    public function userName(): string
    {
        return app(TokenStore::class)->userName() ?? '';
    }

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    public function pendingCount(): int
    {
        return app(PendingChanges::class)->count();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Akun saya</p>
        <h1 class="masthead__title">{{ $this->userName() }}</h1>
        <p class="masthead__note">{{ $this->isPremium() ? 'Langganan premium aktif.' : 'Paket gratis.' }}</p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    <section class="tape">
        <p class="tape__label">Pengaturan</p>

        <a class="entry" href="{{ route('subscription') }}" wire:navigate style="color: inherit; text-decoration: none;">
            <div class="entry__label">
                <p class="entry__title">Langganan</p>
                <p class="entry__meta">Status premium dan perpanjangannya</p>
            </div>
            <span class="entry__amount muted">›</span>
        </a>

        <a class="entry" href="{{ route('books') }}" wire:navigate style="color: inherit; text-decoration: none;">
            <div class="entry__label">
                <p class="entry__title">Buku</p>
                <p class="entry__meta">Pilih atau buat buku kas</p>
            </div>
            <span class="entry__amount muted">›</span>
        </a>
    </section>

    <section class="tape">
        <p class="tape__label">Hapus akun</p>

        @unless ($isDeleting)
            <p class="entry__title" style="margin: 8px 0 0;">Menghapus akun menghapus semuanya.</p>
            <p class="muted small" style="margin: 10px 0 0;">
                Seluruh buku, transaksi, foto struk, invoice, dan tiket bantuan Anda hilang permanen.
                Tidak bisa dibatalkan, dan kami tidak bisa memulihkannya. Ekspor dulu lewat web kalau masih dibutuhkan.
                @if ($this->pendingCount() > 0)
                    {{ $this->pendingCount() }} catatan di ponsel ini juga belum terkirim dan ikut hilang.
                @endif
            </p>

            <button class="button button--quiet" type="button" wire:click="startDeleting" style="margin-top: 20px;">
                Hapus akun saya
            </button>
        @else
            <p class="muted small" style="margin: 8px 0 0;">
                Ketik ulang email dan kata sandi Anda untuk memastikan.
            </p>

            <form wire:submit="deleteAccount">
                <div class="field">
                    <label class="field__label" for="delete-email">Email</label>
                    <input class="field__input" id="delete-email" type="email" inputmode="email"
                           autocomplete="username" placeholder="nama@email.com" wire:model="email">
                    @error('email') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="delete-password">Kata sandi</label>
                    <input class="field__input" id="delete-password" type="password"
                           autocomplete="current-password" placeholder="••••••••" wire:model="password">
                    @error('password') <p class="field__hint">{{ $message }}</p> @enderror
                </div>

                <button class="button" type="submit" wire:loading.attr="disabled"
                        wire:confirm="Hapus akun beserta seluruh isinya? Tindakan ini tidak bisa dibatalkan.">
                    <span wire:loading.remove wire:target="deleteAccount">Hapus permanen</span>
                    <span wire:loading wire:target="deleteAccount">Menghapus…</span>
                </button>
            </form>

            <button class="button button--quiet" type="button" wire:click="cancelDeleting" style="margin-top: 10px;">
                Batal
            </button>
        @endunless
    </section>

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
</div>
