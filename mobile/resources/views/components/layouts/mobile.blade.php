@use('App\Services\DeviceDatabase')
@use('App\Services\TokenStore')

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? 'RakKu' }}</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @livewireStyles
</head>
<body>
    @php
        // Saat penyimpanan rusak, TokenStore pun belum tentu bisa dibaca — jadi ia
        // sengaja tidak disentuh sebelum keadaan itu dipastikan aman.
        $databaseIsBroken = app(DeviceDatabase::class)->hasFailed();
        $hasOpenBook = ! $databaseIsBroken && app(TokenStore::class)->bookPublicId() !== null;
    @endphp

    <main class="shell {{ $hasOpenBook ? 'shell--with-tabs' : '' }}">
        @if ($databaseIsBroken && ! request()->routeIs('trouble'))
            <a class="notice" href="{{ route('trouble') }}" wire:navigate style="display: block; text-decoration: none;">
                Penyimpanan di ponsel ini perlu diperbaiki. Ketuk untuk melihat caranya.
            </a>
        @endif

        {{ $slot }}
    </main>

    {{-- Tab kartu indeks: muncul setelah ada buku yang dibuka, karena semua layarnya butuh itu. --}}
    @if ($hasOpenBook)
        <nav class="tabs">
            <a class="tabs__tab {{ request()->routeIs('home') ? 'tabs__tab--on' : '' }}"
               href="{{ route('home') }}" wire:navigate>Beranda</a>

            <a class="tabs__tab tabs__tab--accent {{ request()->routeIs('record') ? 'tabs__tab--on' : '' }}"
               href="{{ route('record') }}" wire:navigate>Catat</a>

            <a class="tabs__tab {{ request()->routeIs('history') ? 'tabs__tab--on' : '' }}"
               href="{{ route('history') }}" wire:navigate>Riwayat</a>

            <a class="tabs__tab {{ request()->routeIs('debt*') ? 'tabs__tab--on' : '' }}"
               href="{{ route('debts') }}" wire:navigate>Utang</a>
        </nav>
    @endif

    @livewireScripts

    {{--
        NativePHP baru memasang pencegat POST-nya saat halaman selesai dimuat.
        Permintaan Livewire yang berangkat lebih dulu sampai ke PHP tanpa body,
        jadi token CSRF-nya ikut hilang dan dijawab 419 "Halaman ini sudah
        kedaluwarsa". Layar yang menyinkron sendiri menunggu kabar ini dulu,
        bukan memakai wire:init yang menembak sebelum pencegatnya siap.
    --}}
    <script>
        (function () {
            const onDevice = typeof window.AndroidPOST !== 'undefined';
            const intercepted = () => String(window.fetch).includes('X-NativePHP-Req-Id');
            const announce = () => window.dispatchEvent(new Event('bridge-ready'));

            function waitForBridge() {
                if (! onDevice || intercepted()) {
                    announce();

                    return;
                }

                let waited = 0;

                const timer = setInterval(function () {
                    waited += 50;

                    if (intercepted() || waited >= 3000) {
                        clearInterval(timer);
                        announce();
                    }
                }, 50);
            }

            // Alpine mendaftarkan pendengarnya saat Livewire selesai menyala.
            if (window.Livewire) {
                waitForBridge();
            } else {
                document.addEventListener('livewire:initialized', waitForBridge);
            }
        })();
    </script>
</body>
</html>
