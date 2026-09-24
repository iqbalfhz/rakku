<?php

namespace App\Support;

use App\Models\Invoice;

/**
 * Pesan pengantar invoice untuk klien.
 *
 * Ditulis di satu tempat karena dipakai dua jalur: tombol WhatsApp di web dan
 * tombol bagikan di aplikasi ponsel. Kalau kalimatnya berbeda, klien yang bingung.
 */
final class InvoiceMessage
{
    public static function forSharing(Invoice $invoice): string
    {
        $invoice->loadMissing(['book', 'client', 'items']);

        return implode("\n", [
            "Halo {$invoice->client->name},",
            '',
            "Berikut invoice {$invoice->invoice_number} dari {$invoice->book->name}.",
            'Total: '.Rupiah::format($invoice->totalAmount()),
            'Jatuh tempo: '.$invoice->due_date->translatedFormat('d F Y'),
            '',
            'Unduh PDF: '.$invoice->sharedPdfUrl(),
            '',
            'Terima kasih.',
        ]);
    }
}
