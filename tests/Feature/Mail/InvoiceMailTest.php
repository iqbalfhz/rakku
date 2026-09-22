<?php

use App\Mail\InvoiceMail;
use App\Models\Book;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;

it('summarizes the invoice, attaches its PDF, and routes replies to the book owner', function () {
    $owner = User::factory()->create(['email' => 'pemilik@rakku.test']);
    $book = Book::factory()->for($owner)->create(['name' => "Nadi's Fotocopy"]);
    $invoice = Invoice::factory()
        ->for($book)
        ->for(Client::factory()->for($book)->state(['name' => 'CV Maju Jaya']))
        ->sent()
        ->create(['invoice_number' => 'INV-2026-0003']);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 2, 'unit_price' => 40_000]);

    $mailable = new InvoiceMail($invoice);

    $mailable->assertHasSubject("Invoice INV-2026-0003 dari Nadi's Fotocopy");
    $mailable->assertHasReplyTo('pemilik@rakku.test', "Nadi's Fotocopy");
    $mailable->assertSeeInText('Halo CV Maju Jaya');
    $mailable->assertSeeInText("Rp\u{A0}80.000");

    $attachment = $mailable->attachments()[0];

    expect($mailable->attachments())->toHaveCount(1)
        ->and($attachment->as)->toBe('INV-2026-0003.pdf')
        ->and($attachment->mime)->toBe('application/pdf');
});
