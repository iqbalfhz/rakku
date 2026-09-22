<?php

namespace App\Actions;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateInvoicePdf
{
    /**
     * Render invoice menjadi isi file PDF.
     */
    public function handle(Invoice $invoice): string
    {
        $invoice->loadMissing(['book', 'client', 'items']);

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->output();
    }
}
