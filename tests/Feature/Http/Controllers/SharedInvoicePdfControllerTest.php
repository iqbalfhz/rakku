<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;

it('serves the invoice PDF through a valid signed link without login', function () {
    $invoice = Invoice::factory()->sent()->create(['invoice_number' => 'INV-2026-0009']);
    InvoiceItem::factory()->for($invoice)->create();

    $this->get($invoice->sharedPdfUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="INV-2026-0009.pdf"');
});

it('rejects the PDF link when the signature is missing or tampered', function () {
    $invoice = Invoice::factory()->sent()->create();
    $otherInvoice = Invoice::factory()->sent()->create();

    $this->get(route('invoices.shared-pdf', $invoice))->assertForbidden();
    $this->get(str_replace("/invoices/{$invoice->id}/", "/invoices/{$otherInvoice->id}/", $invoice->sharedPdfUrl()))->assertForbidden();
});

it('rejects the PDF link after it expires', function () {
    $invoice = Invoice::factory()->sent()->create();
    $url = $invoice->sharedPdfUrl();

    $this->travel(Invoice::SHARED_PDF_LINK_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
});
