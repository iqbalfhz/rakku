<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Support\InvoiceMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menyiapkan bahan untuk tombol bagikan di ponsel: link PDF bertanda tangan
 * dan pesan pengantarnya, dengan kalimat yang sama seperti tombol WhatsApp di web.
 */
class InvoiceShareController extends Controller
{
    public function store(Request $request, Book $book, string $invoicePublicId): JsonResponse
    {
        abort_unless($book->user_id === $request->user()->id, 404);
        abort_unless($request->user()->isPremium(), 403, 'Invoice hanya untuk pelanggan premium.');

        $invoice = $book->invoices()->where('public_id', $invoicePublicId)->firstOrFail();

        // Mengirim pertama kali mengubah draft menjadi terkirim, sama seperti di web.
        $invoice->markAsSentIfDraft();

        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'url' => $invoice->sharedPdfUrl(),
            'message' => InvoiceMessage::forSharing($invoice),
            'status' => $invoice->status->value,
        ]);
    }
}
