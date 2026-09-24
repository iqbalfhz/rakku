<?php

use App\Services\DeviceDatabase;
use App\Services\DiagnosticsReporter;
use Carbon\CarbonImmutable;
use Livewire\Component;

new class extends Component
{
    public ?string $notice = null;

    /**
     * Coba jalankan ulang migrasi yang gagal. Sering kali penyebabnya sementara —
     * penyimpanan penuh, atau aplikasi mati di tengah pembaruan — dan sekali coba
     * lagi sudah cukup.
     */
    public function retry(DeviceDatabase $deviceDatabase): void
    {
        $deviceDatabase->migrate();

        $this->notice = $deviceDatabase->hasFailed()
            ? 'Masih gagal. Laporannya sudah dicatat dan akan terkirim ke pengembang saat ada sinyal.'
            : 'Berhasil diperbaiki. Aplikasi sudah bisa dipakai seperti biasa.';
    }

    /**
     * @return array{message: string, at: string}|null
     */
    public function failure(): ?array
    {
        return app(DeviceDatabase::class)->failure();
    }

    public function failedAtLabel(): ?string
    {
        $failure = $this->failure();

        return $failure === null ? null : CarbonImmutable::parse($failure['at'])->translatedFormat('j M Y, H:i');
    }

    public function pendingReportCount(): int
    {
        return app(DiagnosticsReporter::class)->pendingCount();
    }
};

?>

<div>
    <header class="masthead">
        <p class="masthead__brand">Ada yang rusak</p>
        <h1 class="masthead__title">Penyimpanan<br>perlu diperbaiki.</h1>
        <p class="masthead__note">Catatan Anda tidak hilang. Yang gagal adalah pembaruan bentuk penyimpanannya.</p>
    </header>

    @if ($notice)
        <p class="notice">{{ $notice }}</p>
    @endif

    @if ($this->failure())
        <section class="tape">
            <p class="tape__label">Yang terjadi</p>

            <p class="entry__title" style="margin: 8px 0 0;">{{ $this->failure()['message'] }}</p>
            <p class="muted small" style="margin: 10px 0 0;">Tercatat {{ $this->failedAtLabel() }}.</p>

            <button class="button" type="button" wire:click="retry" wire:loading.attr="disabled" style="margin-top: 20px;">
                <span wire:loading.remove wire:target="retry">Coba perbaiki</span>
                <span wire:loading wire:target="retry">Memperbaiki…</span>
            </button>
        </section>

        <section class="tape">
            <p class="tape__label">Kalau masih gagal</p>
            <p class="entry__title" style="margin: 8px 0 0;">Pasang ulang aplikasi setelah menyinkronkan.</p>
            <p class="muted small" style="margin: 10px 0 0;">
                Catatan yang sudah sampai ke server akan kembali sendiri setelah Anda masuk lagi.
                @if ($this->pendingReportCount() > 0)
                    Laporan kerusakan sudah disiapkan dan terkirim ke pengembang saat ada sinyal.
                @endif
            </p>
        </section>
    @else
        <section class="tape">
            <p class="entry__title">Tidak ada masalah yang tercatat.</p>
            <p class="muted small" style="margin: 10px 0 0;">Penyimpanan di ponsel ini dalam keadaan baik.</p>
        </section>
    @endif

    <a class="button button--quiet" href="{{ route('home') }}" wire:navigate>Ke beranda</a>
</div>
