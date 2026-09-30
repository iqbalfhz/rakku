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

    {{--
        Tab kartu indeks: muncul setelah ada buku yang dibuka, karena semua
        layarnya butuh itu. Ikonnya digambar dengan gaya garis yang sama seperti
        petak pintasan di beranda, dan mewarisi warna dari tabnya sendiri supaya
        yang sedang aktif ikut menonjol tanpa aturan tambahan.
    --}}
    @if ($hasOpenBook)
        @php
            $tabs = [
                ['route' => 'home', 'active' => 'home', 'label' => 'Beranda', 'accent' => false, 'paths' => [
                    'm3 10.5 9-7.5 9 7.5', 'M5.5 9.5V21h13V9.5',
                ]],
                ['route' => 'record', 'active' => 'record', 'label' => 'Catat', 'accent' => true, 'paths' => [
                    'M12 5v14', 'M5 12h14',
                ]],
                ['route' => 'history', 'active' => 'history', 'label' => 'Riwayat', 'accent' => false, 'paths' => [
                    'M12 3a9 9 0 1 1-9 9', 'M3 3v5h5', 'M12 7v5l3 2',
                ]],
                ['route' => 'debts', 'active' => 'debt*', 'label' => 'Utang', 'accent' => false, 'paths' => [
                    'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7', 'M2 20a7 7 0 0 1 14 0',
                    'M17 10.5a3 3 0 1 0 0-6', 'M17.5 13.5a5.5 5.5 0 0 1 4.5 5.4',
                ]],
            ];
        @endphp

        <nav class="tabs">
            @foreach ($tabs as $tab)
                <a class="tabs__tab {{ $tab['accent'] ? 'tabs__tab--accent' : '' }} {{ request()->routeIs($tab['active']) ? 'tabs__tab--on' : '' }}"
                   href="{{ route($tab['route']) }}" wire:navigate>
                    <svg class="tabs__icon" width="22" height="22" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                         stroke-linejoin="round" aria-hidden="true">
                        @foreach ($tab['paths'] as $d)
                            <path d="{{ $d }}"></path>
                        @endforeach
                    </svg>
                    {{ $tab['label'] }}
                </a>
            @endforeach
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
