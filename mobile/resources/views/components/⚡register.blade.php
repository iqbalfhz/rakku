<?php

use App\Services\ApiClient;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?string $registeredEmail = null;

    public ?string $error = null;

    /**
     * Daftar, lalu berhenti di layar "cek email". Tidak ada token yang diberikan
     * sebelum email diverifikasi — aturan yang sama seperti di web.
     */
    public function submit(ApiClient $apiClient): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ], attributes: [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
        ], messages: [
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $this->error = null;

        try {
            $created = $apiClient->register($data['name'], $data['email'], $data['password']);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->registeredEmail = $created['email'];
        $this->password = '';
    }
};

?>

<div>
    @if ($registeredEmail)
        <header class="masthead">
            <p class="masthead__brand">Tinggal satu langkah</p>
            <h1 class="masthead__title">Cek email<br>Anda.</h1>
            <p class="masthead__note">Kami mengirim tautan verifikasi ke {{ $registeredEmail }}.</p>
        </header>

        <section class="tape">
            <p class="tape__label">Setelah itu</p>
            <p class="entry__title" style="margin: 8px 0 0;">Buka tautannya, lalu kembali ke sini dan masuk.</p>
            <p class="muted small" style="margin: 10px 0 0;">
                Kalau emailnya belum terlihat, coba lihat folder spam. Buku pertama Anda sudah disiapkan.
            </p>
        </section>

        <a class="button" href="{{ route('login') }}" wire:navigate>Masuk sekarang</a>
    @else
        <header class="masthead">
            <p class="masthead__brand">Daftar</p>
            <h1 class="masthead__title">Mulai catat<br>hari ini.</h1>
            <p class="masthead__note">Gratis, dan bisa dipakai tanpa sinyal setelah masuk.</p>
        </header>

        @if ($error)
            <p class="notice">{{ $error }}</p>
        @endif

        <form wire:submit="submit">
            <div class="field">
                <label class="field__label" for="register-name">Nama</label>
                <input class="field__input" id="register-name" type="text" autocomplete="name"
                       placeholder="Budi Santoso" wire:model="name">
                @error('name') <p class="field__hint">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label class="field__label" for="register-email">Email</label>
                <input class="field__input" id="register-email" type="email" inputmode="email"
                       autocomplete="username" placeholder="nama@email.com" wire:model="email">
                @error('email') <p class="field__hint">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label class="field__label" for="register-password">Kata sandi</label>
                <input class="field__input" id="register-password" type="password"
                       autocomplete="new-password" placeholder="••••••••" wire:model="password">
                @error('password') <p class="field__hint">{{ $message }}</p> @enderror
                <p class="field__hint">Minimal 8 karakter.</p>
            </div>

            <button class="button" type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Buat akun</span>
                <span wire:loading wire:target="submit">Mendaftarkan…</span>
            </button>
        </form>

        <a class="button button--quiet" href="{{ route('login') }}" wire:navigate style="margin-top: 12px;">
            Sudah punya akun? Masuk
        </a>
    @endif
</div>
