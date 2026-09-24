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
    <main class="shell {{ app(TokenStore::class)->isSignedIn() ? 'shell--with-tabs' : '' }}">
        {{ $slot }}
    </main>

    {{-- Tab kartu indeks: hanya muncul setelah pengguna masuk. --}}
    @if (app(TokenStore::class)->isSignedIn())
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
</body>
</html>
