<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

it('marks sent invoices past their due date as overdue', function () {
    $this->travelTo('2026-06-15');
    $pastDue = Invoice::factory()->sent()->create(['due_date' => '2026-06-14']);
    $dueToday = Invoice::factory()->sent()->create(['due_date' => '2026-06-15']);
    $draft = Invoice::factory()->create(['due_date' => '2026-06-01']);

    $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

    expect($pastDue->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($dueToday->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($draft->fresh()->status)->toBe(InvoiceStatus::Draft);
});
