<?php

use App\Models\Book;
use App\Models\Invoice;
use App\Models\InvoiceItem;

it('starts invoice numbering at 0001 for each book and year', function () {
    $this->travelTo('2026-02-01');
    $book = Book::factory()->create();
    Invoice::factory()->create(['invoice_number' => 'INV-2026-0005']);
    Invoice::factory()->for($book)->create(['invoice_number' => 'INV-2025-0009']);

    expect(Invoice::nextNumberFor($book))->toBe('INV-2026-0001');
});

it('continues from the highest invoice number of the current year', function () {
    $this->travelTo('2026-02-01');
    $book = Book::factory()->create();
    Invoice::factory()->for($book)->create(['invoice_number' => 'INV-2026-0001']);
    Invoice::factory()->for($book)->create(['invoice_number' => 'INV-2026-0012']);

    expect(Invoice::nextNumberFor($book))->toBe('INV-2026-0013');
});

it('sums quantity times unit price of every item as the total', function () {
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 3, 'unit_price' => 15_000]);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 0.5, 'unit_price' => 10_000]);

    expect(Invoice::withTotalAmount()->find($invoice->id)->totalAmount())->toBe(50_000.0)
        ->and($invoice->fresh()->totalAmount())->toBe(50_000.0);
});
