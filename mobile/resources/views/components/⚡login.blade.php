<?php

use App\Services\ApiClient;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Livewire\Component;

new class extends Component
{
    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    /**
     * Tukar email dan kata sandi dengan token, lalu ingat buku pertama milik pengguna.
     */
    public function submit(ApiClient $apiClient, TokenStore $tokenStore): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], attributes: ['email' => 'email', 'password' => 'kata sandi']);

        $this->error = null;

        try {
            $session = $apiClient->login($this->email, $this->password, $this->deviceName());
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $tokenStore->rememberSession($session['token'], $session['user']['name']);
        $tokenStore->rememberBooks($session['books']);

        $book = collect($session['books'])->firstWhere('is_default', true) ?? $session['books'][0] ?? null;

        // Akun baru belum tentu punya buku; pengguna dibawa membuatnya, bukan disuruh ke web.
        if ($book === null) {
            $this->redirect(route('books'));

            return;
        }

        $tokenStore->rememberBook($book['public_id'], $book['name']);

        $this->pullFirstTime();

        $this->redirect(route('home'));
    }

    /**
     * Tarikan pertama dijalankan di sini supaya beranda tidak tampil kosong.
     * Kalau gagal, pengguna tetap masuk dan bisa menyinkronkan sendiri nanti.
     */
    private function pullFirstTime(): void
    {
        try {
            app(SyncEngine::class)->pull();
        } catch (\Throwable) {
            // Sinyal buruk saat masuk bukan alasan untuk menahan pengguna di layar ini.
        }
    }

    private function deviceName(): string
    {
        return 'Ponsel RakKu';
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">RakKu</p>
        <h1 class="masthead__title">Buku kas<br>dalam saku.</h1>
        <p class="masthead__note">Masuk dengan akun yang sama seperti di web.</p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    <form wire:submit="submit">
        <div class="field">
            <label class="field__label" for="email">Email</label>
            <input class="field__input" id="email" type="email" inputmode="email" autocomplete="username"
                   placeholder="nama@email.com" wire:model="email">
            @error('email') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label class="field__label" for="password">Kata sandi</label>
            <input class="field__input" id="password" type="password" autocomplete="current-password"
                   placeholder="••••••••" wire:model="password">
            @error('password') <p class="field__hint">{{ $message }}</p> @enderror
        </div>

        <button class="button" type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Masuk</span>
            <span wire:loading wire:target="submit">Menghubungi server…</span>
        </button>
    </form>

    <p class="muted small" style="margin-top: 28px;">
        Belum punya akun? Daftar dulu lewat rakku.iqbalfhz.my.id, lalu masuk dari sini.
    </p>
</div>
