@use('App\Support\Rupiah')

<x-filament-panels::page>
    {{-- Ringkasan paket yang sedang berjalan --}}
    <x-filament::section>
        <x-slot name="heading">Status langganan</x-slot>

        @if ($this->user()->isPremium())
            <div class="flex items-center gap-2">
                <x-filament::badge color="warning">Premium</x-filament::badge>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $this->premiumExpiresAt() ? 'Berlaku sampai ' . $this->premiumExpiresAt() : 'Berlaku tanpa batas waktu' }}
                </span>
            </div>
        @else
            <div class="flex items-center gap-2">
                <x-filament::badge color="gray">Free</x-filament::badge>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Utang-piutang, invoice, laporan laba-rugi, transaksi berulang, dan buku tambahan terbuka setelah premium aktif.
                </span>
            </div>
        @endif
    </x-filament::section>

    {{-- Nomor rekening dan harga tiap paket --}}
    <x-filament::section>
        <x-slot name="heading">Cara berlangganan</x-slot>
        <x-slot name="description">Transfer sesuai paket, lalu unggah buktinya. Admin memverifikasi secara manual.</x-slot>

        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Bank</dt>
                <dd class="font-medium">{{ $this->bankAccount()['name'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Nomor rekening</dt>
                <dd class="font-medium">{{ $this->bankAccount()['account_number'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Atas nama</dt>
                <dd class="font-medium">{{ $this->bankAccount()['account_holder'] }}</dd>
            </div>
        </dl>

        <ul class="mt-6 divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->packages() as $package)
                <li class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $package->months() }} bulan</span>
                    <span class="font-medium">{{ Rupiah::format($package->price()) }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>

    {{-- Form pengajuan, disembunyikan selama masih ada yang menunggu verifikasi --}}
    @if ($payment = $this->pendingPayment())
        <x-filament::section>
            <x-slot name="heading">Menunggu verifikasi</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Bukti transfer paket {{ $payment->package->months() }} bulan sebesar
                {{ Rupiah::format((float) $payment->amount) }} sudah kami terima pada
                {{ $payment->created_at->translatedFormat('j F Y, H:i') }}.
                Anda akan diberi tahu di aplikasi ini begitu admin menyetujuinya.
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Kirim bukti transfer</x-slot>

            <form wire:submit="submit" class="space-y-6">
                {{ $this->form }}

                <x-filament::button type="submit">
                    Kirim bukti transfer
                </x-filament::button>
            </form>
        </x-filament::section>
    @endif

    {{-- Riwayat pengajuan sebelumnya --}}
    @if ($this->paymentHistory()->isNotEmpty())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Riwayat pengajuan</x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($this->paymentHistory() as $payment)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <span>
                            {{ $payment->created_at->translatedFormat('j M Y') }} —
                            {{ $payment->package->months() }} bulan,
                            {{ Rupiah::format((float) $payment->amount) }}
                        </span>

                        <span class="flex items-center gap-2">
                            @if ($payment->rejection_reason)
                                <span class="text-gray-500 dark:text-gray-400">{{ $payment->rejection_reason }}</span>
                            @endif

                            <x-filament::badge :color="$payment->status->getColor()">
                                {{ $payment->status->getLabel() }}
                            </x-filament::badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
