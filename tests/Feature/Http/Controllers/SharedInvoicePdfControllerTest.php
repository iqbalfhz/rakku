<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

it('serves the invoice PDF through a valid signed link without login', function () {
    $invoice = Invoice::factory()->sent()->create(['invoice_number' => 'INV-2026-0009']);
    InvoiceItem::factory()->for($invoice)->create();

    $this->get($invoice->sharedPdfUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="INV-2026-0009.pdf"');
});

it('keeps HTTPS PDF links valid behind the reverse proxy', function () {
    $invoice = Invoice::factory()->sent()->create();
    InvoiceItem::factory()->for($invoice)->create();
    URL::forceScheme('https');
    $url = $invoice->sharedPdfUrl();

    $this->get(str_replace('https://', 'http://', $url), ['X-Forwarded-Proto' => 'https'])
        ->assertOk();
});

it('rejects the PDF link when the signature is missing or tampered', function () {
    $invoice = Invoice::factory()->sent()->create();
    $otherInvoice = Invoice::factory()->sent()->create();

    $this->get(route('invoices.shared-pdf', $invoice))->assertForbidden();
    $this->get(str_replace($invoice->public_id, $otherInvoice->public_id, $invoice->sharedPdfUrl()))->assertForbidden();
});

it('addresses the shared PDF by a random public id instead of the sequential database id', function () {
    $invoice = Invoice::factory()->sent()->create();
    InvoiceItem::factory()->for($invoice)->create();

    expect(Str::isUlid($invoice->public_id))->toBeTrue()
        ->and($invoice->sharedPdfUrl())->toContain("/invoices/{$invoice->public_id}/pdf");

    $this->get(URL::signedRoute('invoices.shared-pdf', ['invoice' => $invoice->id]))->assertNotFound();
});

it('rejects the PDF link after it expires', function () {
    $invoice = Invoice::factory()->sent()->create();
    $url = $invoice->sharedPdfUrl();

    $this->travel(Invoice::SHARED_PDF_LINK_DAYS + 1)->days();

    $this->get($url)->assertForbidden();
});
