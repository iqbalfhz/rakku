<?php

namespace App\Http\Controllers;

use App\Actions\GenerateInvoicePdf;
use App\Models\Invoice;
use Illuminate\Http\Response;

/**
 * PDF invoice untuk klien lewat link bertanda tangan (tanpa login), dibagikan via WhatsApp.
 */
class SharedInvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice, GenerateInvoicePdf $generateInvoicePdf): Response
    {
        return response($generateInvoicePdf->handle($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$invoice->invoice_number}.pdf\"",
        ]);
    }
}
