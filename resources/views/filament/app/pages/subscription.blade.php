@use('App\Support\Rupiah')

<x-filament-panels::page>
    {{ $this->overview }}

    {{-- Form pengajuan disembunyikan selama masih ada yang menunggu verifikasi --}}
    @if ($payment = $this->pendingPayment())
        <x-filament::section icon="heroicon-o-clock" icon-color="warning">
            <x-slot name="heading">Menunggu verifikasi</x-slot>

            <p>
                Bukti transfer paket {{ $payment->package->months() }} bulan sebesar
                {{ Rupiah::format((float) $payment->amount) }} sudah kami terima pada
                {{ $payment->created_at->translatedFormat('j F Y, H:i') }}.
                Anda akan diberi tahu di aplikasi ini begitu admin menyetujuinya.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Kirim bukti transfer</x-slot>

            <form wire:submit="submit">
                {{ $this->form }}

                <x-filament::button type="submit" class="mt-6">
                    Kirim bukti transfer
                </x-filament::button>
            </form>
        </x-filament::section>
    @endif

    {{ $this->history }}
</x-filament-panels::page>
