<?php

namespace App\Actions;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

class SendInvoiceByEmail
{
    /**
     * Kirim invoice beserta PDF-nya ke email klien; bisa dipanggil berulang untuk kirim ulang.
     */
    public function handle(Invoice $invoice, string $email): void
    {
        Mail::to($email, $invoice->client->name)->send(new InvoiceMail($invoice));

        $invoice->markAsSentIfDraft();
    }
}
