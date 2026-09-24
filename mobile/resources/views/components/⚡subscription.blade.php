<?php

use App\Services\ApiClient;
use App\Services\PlanGate;
use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Native\Mobile\Facades\Camera;

new class extends Component
{
    /**
     * Isi halaman langganan dari server: harga, rekening, dan riwayat pengajuan.
     *
     * @var array<string, mixed>|null
     */
    public ?array $details = null;

    public string $package = 'monthly';

    public string $note = '';

    public ?string $proofPath = null;

    public ?string $error = null;

    public ?string $notice = null;

    public function mount(ApiClient $apiClient): void
    {
        $this->load($apiClient);
    }

    /**
     * Semua isi layar ini datang dari server: harga bisa berubah, dan status
     * pengajuan hanya admin yang tahu. Tidak ada yang layak ditebak dari ponsel.
     */
    public function load(ApiClient $apiClient): void
    {
        $this->error = null;

        try {
            $this->details = $apiClient->subscription();
        } catch (\Throwable) {
            $this->error = 'Halaman langganan butuh sinyal. Coba lagi sebentar.';
        }
    }

    public function takePhoto(): void
    {
        Camera::getPhoto();
    }

    /**
     * Foto disalin ke penyimpanan aplikasi supaya tidak hilang saat folder kamera dibersihkan.
     */
    #[On('native:Native\Mobile\Events\Camera\PhotoTaken')]
    public function photoTaken(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $storedPath = 'payment-proofs/'.Str::lower((string) Str::ulid()).'.jpg';

        Storage::disk('local')->put($storedPath, file_get_contents($path));

        $this->proofPath = $storedPath;
    }

    public function removePhoto(): void
    {
        if ($this->proofPath !== null) {
            Storage::disk('local')->delete($this->proofPath);
        }

        $this->proofPath = null;
    }

    public function submit(ApiClient $apiClient, PlanGate $planGate): void
    {
        $this->error = null;
        $this->notice = null;

        $data = $this->validate([
            'package' => ['required', 'in:monthly,quarterly,yearly'],
            'proofPath' => ['required'],
            'note' => ['nullable', 'string', 'max:500'],
        ], attributes: ['proofPath' => 'bukti transfer', 'note' => 'catatan'], messages: [
            'proofPath.required' => 'Fotokan dulu bukti transfernya.',
        ]);

        try {
            $apiClient->submitPaymentProof(
                $data['package'],
                Storage::disk('local')->path($this->proofPath),
                $data['note'] ?: null,
            );
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        // Bukti sudah aman di server; salinan di ponsel tidak perlu memakan ruang lagi.
        Storage::disk('local')->delete($this->proofPath);

        $this->proofPath = null;
        $this->note = '';
        $this->notice = 'Bukti transfer terkirim. Admin akan memeriksanya, dan premium menyala begitu disetujui.';

        $this->load($apiClient);
    }

    public function isPremium(): bool
    {
        return app(PlanGate::class)->isPremium();
    }

    public function expiryLabel(): ?string
    {
        $expiresAt = $this->details['plan']['expires_at'] ?? null;

        return $expiresAt === null ? null : CarbonImmutable::parse($expiresAt)->translatedFormat('j F Y');
    }

    /**
     * @return list<array{value: string, months: int, price: int}>
     */
    public function packages(): array
    {
        return $this->details['packages'] ?? [];
    }

    public function photoDataUri(): ?string
    {
        if ($this->proofPath === null || ! Storage::disk('local')->exists($this->proofPath)) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($this->proofPath));
    }

    public function packageLabel(int $months, float $price): string
    {
        return "{$months} bulan — ".Rupiah::format($price);
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Langganan</p>
        <h1 class="masthead__title">{{ $this->isPremium() ? 'Premium aktif.' : 'Buka semua fitur.' }}</h1>
        <p class="masthead__note">
            @if ($this->isPremium() && $this->expiryLabel())
                Berlaku sampai {{ $this->expiryLabel() }}.
            @elseif ($this->isPremium())
                Berlaku tanpa batas waktu.
            @else
                Utang-piutang, invoice, laba-rugi, dan transaksi berulang.
            @endif
        </p>
    </header>

    @if ($error)
        <p class="notice">{{ $error }}</p>
    @endif

    @if ($notice)
        <p class="notice">{{ $notice }}</p>
    @endif

    @if ($details === null)
        <section class="tape">
            <p class="entry__title muted">Belum bisa memuat halaman ini.</p>
            <button class="button button--quiet" type="button" wire:click="load" style="margin-top: 16px;">Coba lagi</button>
        </section>

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate style="margin-top: 10px;">Kembali</a>
    @else
        @if ($details['pending_payment'])
            <section class="tape">
                <p class="tape__label">Sedang diperiksa</p>
                <p class="entry__title" style="margin: 8px 0 0;">
                    {{ $details['pending_payment']['months'] }} bulan — {{ Rupiah::format($details['pending_payment']['amount']) }}
                </p>
                <p class="muted small" style="margin: 10px 0 0;">
                    Bukti Anda sudah sampai. Admin akan memeriksanya, dan premium menyala begitu disetujui.
                </p>
            </section>
        @else
            <section class="tape">
                <p class="tape__label">Transfer ke</p>

                <div class="entry">
                    <div class="entry__label">
                        <p class="entry__title">{{ $details['bank']['account_number'] }}</p>
                        <p class="entry__meta">{{ $details['bank']['name'] }} · a.n. {{ $details['bank']['account_holder'] }}</p>
                    </div>
                </div>

                <p class="muted small" style="margin: 14px 0 0;">
                    Transfer sesuai paket yang dipilih, lalu fotokan buktinya di bawah.
                </p>
            </section>

            <section class="tape">
                <p class="tape__label">Pilih paket</p>

                <form wire:submit="submit">
                    <div class="field">
                        @foreach ($this->packages() as $option)
                            <label class="entry" style="cursor: pointer;">
                                <div class="entry__label">
                                    <p class="entry__title">
                                        <input type="radio" value="{{ $option['value'] }}" wire:model.live="package"
                                               style="margin-right: 8px;">{{ $option['months'] }} bulan
                                    </p>
                                </div>
                                <span class="entry__amount">{{ Rupiah::format($option['price']) }}</span>
                            </label>
                        @endforeach
                        @error('package') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <span class="field__label">Bukti transfer</span>

                        @if ($this->photoDataUri())
                            <img class="receipt" src="{{ $this->photoDataUri() }}" alt="Bukti transfer">

                            <div class="entry" style="border-bottom: 0;">
                                <span class="stamp">Foto terpasang</span>
                                <button class="linkish" type="button" wire:click="removePhoto">Ganti foto</button>
                            </div>
                        @else
                            <button class="button button--quiet" type="button" wire:click="takePhoto">Fotokan bukti transfer</button>
                        @endif

                        @error('proofPath') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="payment-note">Catatan</label>
                        <input class="field__input" id="payment-note" type="text"
                               placeholder="Nama pengirim, kalau berbeda dengan nama akun" wire:model="note">
                        @error('note') <p class="field__hint">{{ $message }}</p> @enderror
                    </div>

                    <button class="button" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">Kirim bukti transfer</span>
                        <span wire:loading wire:target="submit">Mengirim…</span>
                    </button>
                </form>
            </section>
        @endif

        @if ($details['payments'])
            <section class="tape">
                <p class="tape__label">Riwayat</p>

                @foreach ($details['payments'] as $payment)
                    <div class="entry">
                        <div class="entry__label">
                            <p class="entry__title">{{ $payment['months'] }} bulan</p>
                            <p class="entry__meta">
                                {{ \Carbon\CarbonImmutable::parse($payment['submitted_at'])->translatedFormat('j M Y') }}
                                · {{ $payment['status_label'] }}
                            </p>
                        </div>
                        <span class="entry__amount">{{ Rupiah::format($payment['amount']) }}</span>
                    </div>
                @endforeach
            </section>
        @endif

        <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Kembali</a>
    @endif
</div>
