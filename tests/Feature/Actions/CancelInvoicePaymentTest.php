<?php

use App\Actions\CancelInvoicePayment;
use App\Actions\MarkInvoiceAsPaid;
use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;

it('removes the income transaction and returns the invoice to sent', function () {
    $invoice = Invoice::factory()->sent()->create();
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => 80_000]);
    $account = Account::factory()->for($invoice->book)->create(['initial_balance' => 0]);
    app(MarkInvoiceAsPaid::class)->handle($invoice, $account, today());
    $transaction = $invoice->fresh()->transaction;

    app(CancelInvoicePayment::class)->handle($invoice->fresh());

    $this->assertSoftDeleted($transaction);
    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Sent)
        ->transaction_id->toBeNull()
        ->and($account->fresh()->current_balance)->toBe('0.00');
});

it('returns a cancelled payment to overdue when the due date has passed', function () {
    $invoice = Invoice::factory()->overdue()->create();
    InvoiceItem::factory()->for($invoice)->create();
    $account = Account::factory()->for($invoice->book)->create();
    app(MarkInvoiceAsPaid::class)->handle($invoice, $account, today());

    app(CancelInvoicePayment::class)->handle($invoice->fresh());

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});
