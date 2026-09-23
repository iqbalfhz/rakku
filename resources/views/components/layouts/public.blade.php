@use('App\Support\PublicContact')

@props([
    'title',
    'description' => 'Buku kas digital untuk usaha kecil dan keuangan pribadi.',
    'loginUrl',
    'registerUrl',
])

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @vite('resources/css/app.css')
</head>
<body class="overflow-x-hidden bg-paper font-sans text-ink antialiased">

{{-- Kepala halaman --}}
<header class="border-b border-rule">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5">
        <a href="{{ route('landing') }}" class="font-serif text-2xl tracking-tight">RakKu</a>

        <nav class="hidden items-center gap-8 text-sm text-ink-soft lg:flex">
            <a href="{{ route('landing') }}#fitur" class="transition hover:text-ink">Fitur</a>
            <a href="{{ route('landing') }}#cara-kerja" class="transition hover:text-ink">Cara kerja</a>
            <a href="{{ route('landing') }}#harga" class="transition hover:text-ink">Harga</a>
            <a href="{{ route('landing') }}#tanya-jawab" class="transition hover:text-ink">Tanya jawab</a>
        </nav>

        <div class="flex shrink-0 items-center gap-2 text-sm sm:gap-3">
            <a href="{{ $loginUrl }}" class="px-2 py-1 text-ink-soft transition hover:text-ink">Masuk</a>
            <a href="{{ $registerUrl }}" class="rounded-full bg-ink px-4 py-2 font-medium text-paper transition hover:bg-accent">
                Daftar<span class="hidden sm:inline"> gratis</span>
            </a>
        </div>
    </div>
</header>

{{ $slot }}

<footer class="border-t border-rule">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 text-sm sm:grid-cols-3 sm:px-6">
        <div>
            <p class="font-serif text-2xl">RakKu</p>
            <p class="mt-2 leading-relaxed text-ink-soft">Buku kas digital untuk usaha kecil dan keuangan pribadi.</p>
        </div>

        <div>
            <p class="text-xs tracking-widest text-ink-soft uppercase">Aplikasi</p>
            <div class="mt-3 flex flex-col gap-2">
                <a href="{{ $loginUrl }}" class="text-ink-soft transition hover:text-ink">Masuk</a>
                <a href="{{ $registerUrl }}" class="text-ink-soft transition hover:text-ink">Daftar</a>
                <a href="{{ route('legal.privacy') }}" class="text-ink-soft transition hover:text-ink">Kebijakan privasi</a>
                <a href="{{ route('legal.terms') }}" class="text-ink-soft transition hover:text-ink">Syarat layanan</a>
            </div>
        </div>

        <div>
            <p class="text-xs tracking-widest text-ink-soft uppercase">Tanya dulu?</p>
            <div class="mt-3 flex flex-col gap-2">
                @if (PublicContact::whatsAppUrl() !== null)
                    <a href="{{ PublicContact::whatsAppUrl() }}" target="_blank" rel="noopener" class="text-ink-soft transition hover:text-ink">
                        WhatsApp {{ PublicContact::whatsAppNumber() }}
                    </a>
                @endif

                @if (PublicContact::email() !== null)
                    <a href="mailto:{{ PublicContact::email() }}" class="text-ink-soft transition hover:text-ink">
                        {{ PublicContact::email() }}
                    </a>
                @endif

                <p class="text-ink-soft">Sudah punya akun? Kirim tiket bantuan dari dalam aplikasi.</p>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl border-t border-rule px-4 py-6 text-sm text-ink-soft sm:px-6">
        © {{ now()->year }} RakKu
    </div>
</footer>

</body>
</html>
