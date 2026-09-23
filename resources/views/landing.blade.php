<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RakKu — Pembukuan rapi untuk usaha kecil</title>
    <meta name="description" content="Catat pemasukan dan pengeluaran, pantau utang-piutang, dan kirim invoice ber-PDF. RakKu adalah buku kas digital untuk usaha kecil dan keuangan pribadi.">
    @vite('resources/css/app.css')
</head>
<body class="overflow-x-hidden bg-paper font-sans text-ink antialiased">

{{-- Kepala halaman --}}
<header class="border-b border-rule">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5">
        <a href="#" class="font-serif text-2xl tracking-tight">RakKu</a>

        <nav class="hidden items-center gap-8 text-sm text-ink-soft lg:flex">
            <a href="#fitur" class="transition hover:text-ink">Fitur</a>
            <a href="#cara-kerja" class="transition hover:text-ink">Cara kerja</a>
            <a href="#harga" class="transition hover:text-ink">Harga</a>
        </nav>

        <div class="flex shrink-0 items-center gap-2 text-sm sm:gap-3">
            <a href="{{ $loginUrl }}" class="px-2 py-1 text-ink-soft transition hover:text-ink">Masuk</a>
            <a href="{{ $registerUrl }}" class="rounded-full bg-ink px-4 py-2 font-medium text-paper transition hover:bg-accent">
                Daftar<span class="hidden sm:inline"> gratis</span>
            </a>
        </div>
    </div>
</header>

{{-- Hero: janji utama di kiri, contoh buku kas di kanan --}}
<section class="relative overflow-hidden border-b border-rule">
    <div class="pointer-events-none absolute inset-0 opacity-60"
         style="background-image: repeating-linear-gradient(to bottom, transparent, transparent 47px, var(--color-rule) 47px, var(--color-rule) 48px);"></div>

    <div class="relative mx-auto grid max-w-6xl gap-14 px-4 py-16 sm:gap-16 sm:px-6 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-28">
        <div>
            <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-rule bg-paper px-3 py-1 text-xs tracking-widest text-ink-soft uppercase">
                Buku kas digital
            </p>

            <h1 class="font-serif text-4xl leading-[1.1] tracking-tight sm:text-5xl md:text-6xl lg:text-7xl">
                Pembukuan yang<br class="hidden sm:inline">
                rapi, tanpa
                <span class="relative whitespace-nowrap italic text-accent">
                    rumus
                    <svg class="absolute -bottom-2 left-0 w-full" height="10" viewBox="0 0 200 10" fill="none" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M2 7C40 3 90 2 130 5C155 6.8 178 7.5 198 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </span>
            </h1>

            <p class="mt-8 max-w-lg text-lg leading-relaxed text-ink-soft">
                Catat uang masuk dan keluar seperti menulis di buku kas, lalu biarkan RakKu yang menghitung saldo,
                menagih utang, dan menerbitkan invoice ber-PDF.
            </p>

            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                <a href="{{ $registerUrl }}" class="rounded-full bg-accent px-7 py-3.5 text-center font-medium text-paper transition hover:bg-ink">
                    Mulai gratis
                </a>
                <a href="#harga" class="rounded-full border border-ink px-7 py-3.5 text-center font-medium transition hover:bg-ink hover:text-paper">
                    Lihat harga
                </a>
            </div>

            <p class="mt-6 text-sm text-ink-soft">
                Tanpa kartu kredit. Fitur harian gratis selamanya.
            </p>
        </div>

        {{-- Contoh buku kas, dibuat dari HTML biasa supaya ringan --}}
        <div class="relative">
            <div class="absolute -inset-3 -rotate-2 rounded-2xl border border-rule bg-paper-deep" aria-hidden="true"></div>

            <div class="relative rotate-1 rounded-2xl border border-rule bg-white p-6 shadow-[0_24px_60px_-30px_rgba(28,27,24,0.45)] sm:p-8">
                <div class="flex items-baseline justify-between rule-row pb-4">
                    <p class="font-serif text-xl">Nadi's Fotocopy</p>
                    <p class="text-xs tracking-widest text-ink-soft uppercase">Oktober</p>
                </div>

                <dl class="mt-5 space-y-4 text-sm">
                    @foreach ($ledgerRows as $row)
                        <div class="flex items-center justify-between gap-4 rule-row pb-4">
                            <div class="min-w-0">
                                <dt>{{ $row['label'] }}</dt>
                                <dd class="text-xs text-ink-soft">{{ $row['date'] }}</dd>
                            </div>
                            <dd class="shrink-0 font-medium tabular-nums {{ $row['isIncome'] ? 'text-accent' : 'text-debit' }}">
                                {{ $row['isIncome'] ? '+' : '−' }}{{ $row['amount'] }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-6 flex items-baseline justify-between">
                    <p class="text-xs tracking-widest text-ink-soft uppercase">Saldo kas</p>
                    <p class="font-serif text-3xl tabular-nums">{{ $ledgerBalance }}</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Tiga janji singkat --}}
<section class="border-b border-rule bg-paper-deep">
    <div class="mx-auto grid max-w-6xl gap-px bg-rule sm:grid-cols-3">
        @foreach ($promises as $promise)
            <div class="bg-paper-deep px-4 py-8 sm:px-6 sm:py-10">
                <p class="font-serif text-2xl">{{ $promise['title'] }}</p>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $promise['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Daftar fitur, disusun seperti baris buku kas --}}
<section id="fitur" class="border-b border-rule">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20 lg:py-28">
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">
            <div>
                <h2 class="font-serif text-4xl leading-tight sm:text-5xl">
                    Yang bisa<br>Anda kerjakan
                </h2>
                <p class="mt-5 max-w-sm leading-relaxed text-ink-soft">
                    Pencatatan harian terbuka untuk semua. Yang bertanda
                    <span class="font-medium text-accent">premium</span> menyusul saat usaha Anda mulai menagih dan berutang.
                </p>
            </div>

            <div>
                <div class="flex items-center justify-between rule-row pb-3 text-xs tracking-widest text-ink-soft uppercase">
                    <span>Fitur</span>
                    <span>Paket</span>
                </div>

                @foreach ($features as $feature)
                    <div class="flex items-center justify-between gap-4 rule-row py-4">
                        <p class="min-w-0">{{ $feature['name'] }}</p>
                        @if ($feature['isFree'])
                            <span class="shrink-0 text-sm text-ink-soft">Gratis</span>
                        @else
                            <span class="shrink-0 rounded-full bg-accent-soft px-3 py-1 text-xs font-medium tracking-wide text-accent uppercase">
                                Premium
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- Tiga langkah pemakaian --}}
<section id="cara-kerja" class="border-b border-rule bg-paper-deep">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20 lg:py-28">
        <h2 class="font-serif text-4xl leading-tight sm:text-5xl">Cukup tiga langkah</h2>

        <div class="mt-14 grid gap-12 sm:grid-cols-3 sm:gap-8">
            @foreach ($steps as $index => $step)
                <div class="relative">
                    <p class="font-serif text-6xl italic text-rule">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                    <p class="mt-3 font-serif text-2xl">{{ $step['title'] }}</p>
                    <p class="mt-2 leading-relaxed text-ink-soft">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Harga, ditampilkan seperti struk --}}
<section id="harga" class="border-b border-rule">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20 lg:py-28">
        <div class="max-w-2xl">
            <h2 class="font-serif text-4xl leading-tight sm:text-5xl">Harga premium</h2>
            <p class="mt-5 leading-relaxed text-ink-soft">
                Bayar lewat transfer, unggah buktinya, dan premium menyala setelah kami periksa.
                Berhenti kapan saja — catatan Anda tetap utuh dan tetap bisa dibuka.
            </p>
        </div>

        <div class="mt-12 grid gap-8 sm:mt-14 sm:gap-6 lg:grid-cols-3">
            @foreach ($packages as $package)
                <div @class([
                    'relative flex flex-col rounded-2xl border p-8',
                    'border-accent bg-accent-soft/40' => $package['isBestValue'],
                    'border-rule bg-paper-deep' => ! $package['isBestValue'],
                ])>
                    @if ($package['isBestValue'])
                        <span class="absolute -top-3 left-8 rounded-full bg-accent px-3 py-1 text-xs font-medium tracking-wide text-paper uppercase">
                            Paling hemat
                        </span>
                    @endif

                    <p class="text-xs tracking-widest text-ink-soft uppercase">{{ $package['months'] }} bulan</p>
                    <p class="mt-4 font-serif text-4xl tabular-nums">{{ $package['price'] }}</p>
                    <p class="mt-2 text-sm text-ink-soft">Setara {{ $package['pricePerMonth'] }} per bulan</p>

                    <a href="{{ $registerUrl }}" @class([
                        'mt-8 rounded-full px-6 py-3 text-center font-medium transition',
                        'bg-accent text-paper hover:bg-ink' => $package['isBestValue'],
                        'border border-ink hover:bg-ink hover:text-paper' => ! $package['isBestValue'],
                    ])>
                        Pilih paket ini
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Ajakan penutup --}}
<section class="border-b border-rule bg-ink text-paper">
    <div class="mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 py-16 sm:px-6 sm:py-20 lg:flex-row lg:items-center lg:justify-between lg:py-24">
        <h2 class="max-w-xl font-serif text-4xl leading-tight sm:text-5xl">
            Buku kas Anda menunggu untuk diisi.
        </h2>

        <a href="{{ $registerUrl }}" class="w-full rounded-full bg-paper px-8 py-4 text-center font-medium text-ink transition hover:bg-accent hover:text-paper sm:w-auto">
            Buat akun gratis
        </a>
    </div>
</section>

<footer class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-10 text-sm text-ink-soft sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p>© {{ now()->year }} RakKu</p>
    <div class="flex gap-6">
        <a href="{{ $loginUrl }}" class="transition hover:text-ink">Masuk</a>
        <a href="{{ $registerUrl }}" class="transition hover:text-ink">Daftar</a>
    </div>
</footer>

</body>
</html>
