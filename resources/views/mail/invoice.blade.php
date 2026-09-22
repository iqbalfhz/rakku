@use(App\Support\Rupiah)
<x-mail::message>
# Invoice {{ $invoice->invoice_number }}

Halo {{ $invoice->client->name }},

Berikut invoice dari **{{ $invoice->book->name }}**. File PDF-nya terlampir pada email ini.

<x-mail::table>
| | |
|:--|--:|
| Tanggal terbit | {{ $invoice->issue_date->translatedFormat('d F Y') }} |
| Jatuh tempo | {{ $invoice->due_date->translatedFormat('d F Y') }} |
| **Total** | **{{ Rupiah::format($invoice->totalAmount()) }}** |
</x-mail::table>

@if ($invoice->notes)
**Catatan:** {{ $invoice->notes }}
@endif

Jika ada pertanyaan, cukup balas email ini.

Terima kasih,<br>
{{ $invoice->book->name }}
</x-mail::message>
