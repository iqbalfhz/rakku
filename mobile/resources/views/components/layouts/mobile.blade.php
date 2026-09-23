<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'RakKu' }}</title>

    {{-- Gaya ditulis langsung di sini supaya aplikasi ponsel tidak perlu proses build aset. --}}
    <style>
        :root {
            --paper: #faf7f1;
            --ink: #1c1b18;
            --ink-soft: #57544c;
            --rule: #ded5c6;
            --accent: #047857;
            --debit: #b4462f;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom);
        }

        .screen { padding: 24px 20px 32px; max-width: 520px; margin: 0 auto; }

        h1 { font-size: 28px; line-height: 1.2; margin: 0 0 8px; }
        h2 { font-size: 20px; margin: 0 0 4px; }
        p.lead { color: var(--ink-soft); margin: 0 0 28px; }

        label { display: block; font-size: 14px; margin-bottom: 6px; color: var(--ink-soft); }

        input[type="email"], input[type="password"], input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            border: 1px solid var(--rule);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
        }

        input:focus, select:focus, textarea:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

        .field { margin-bottom: 18px; }

        button {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            border: 0;
            border-radius: 999px;
            background: var(--accent);
            color: var(--paper);
        }

        button[disabled] { opacity: .6; }

        button.ghost { background: transparent; color: var(--ink-soft); border: 1px solid var(--rule); }

        .card {
            background: #fff;
            border: 1px solid var(--rule);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .error {
            background: #fdecea;
            border: 1px solid #f3c2bb;
            color: var(--debit);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .muted { color: var(--ink-soft); font-size: 14px; }
    </style>

    @livewireStyles
</head>
<body>
    <main class="screen">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
