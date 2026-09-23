<?php

use App\Services\ApiClient;
use App\Services\TokenStore;
use Livewire\Component;
use RuntimeException;

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
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $tokenStore->rememberSession($session['token'], $session['user']['name']);

        $book = collect($session['books'])->firstWhere('is_default', true) ?? $session['books'][0] ?? null;

        if ($book === null) {
            $this->error = 'Akun ini belum punya buku. Buat dulu lewat aplikasi web.';

            return;
        }

        $tokenStore->rememberBook($book['public_id'], $book['name']);

        $this->redirect(route('home'));
    }

    private function deviceName(): string
    {
        return 'Ponsel RakKu';
    }
};

?>

<div>
    <h1>Masuk ke RakKu</h1>
    <p class="lead">Pakai akun yang sama dengan aplikasi webnya.</p>

    @if ($error)
        <p class="error">{{ $error }}</p>
    @endif

    <form wire:submit="submit">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" inputmode="email" autocomplete="username" wire:model="email">
            @error('email') <p class="muted">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="password">Kata sandi</label>
            <input id="password" type="password" autocomplete="current-password" wire:model="password">
            @error('password') <p class="muted">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Masuk</span>
            <span wire:loading wire:target="submit">Menghubungi server…</span>
        </button>
    </form>
</div>
