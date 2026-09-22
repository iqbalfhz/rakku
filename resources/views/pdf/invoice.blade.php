@use(App\Support\Rupiah)
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 24px; margin: 0; color: #047857; }
        .muted { color: #6b7280; }
        .header, .parties { width: 100%; margin-bottom: 24px; }
        .header td, .parties td { vertical-align: top; }
        .text-right { text-align: right; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; background: #ecfdf5; color: #047857; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #047857; color: #fff; padding: 8px; text-align: left; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        table.items tfoot td { font-weight: bold; font-size: 14px; border-bottom: none; }
        .notes { margin-top: 24px; white-space: pre-line; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>INVOICE</h1>
                <div class="muted">{{ $invoice->invoice_number }}</div>
            </td>
            <td class="text-right">
                <strong>{{ $invoice->book->name }}</strong><br>
                <span class="status">{{ $invoice->status->getLabel() }}</span>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="muted">Ditagihkan kepada</div>
                <strong>{{ $invoice->client->name }}</strong><br>
                @if ($invoice->client->email) {{ $invoice->client->email }}<br> @endif
                @if ($invoice->client->phone) {{ $invoice->client->phone }}<br> @endif
                @if ($invoice->client->address) {{ $invoice->client->address }} @endif
            </td>
            <td class="text-right">
                <div><span class="muted">Tanggal terbit:</span> {{ $invoice->issue_date->translatedFormat('d F Y') }}</div>
                <div><span class="muted">Jatuh tempo:</span> {{ $invoice->due_date->translatedFormat('d F Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Harga satuan</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-right">{{ Rupiah::format((float) $item->unit_price) }}</td>
                    <td class="text-right">{{ Rupiah::format($item->subtotal()) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right">Total</td>
                <td class="text-right">{{ Rupiah::format($invoice->totalAmount()) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <div class="muted">Catatan</div>
            {{ $invoice->notes }}
        </div>
    @endif
</body>
</html>
