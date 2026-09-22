<?php

use App\Actions\MarkInvoiceAsPaid;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceItem;

it('marks a sent invoice as paid and records the income on the chosen account', function () {
    $invoice = Invoice::factory()->sent()->create(['invoice_number' => 'INV-2026-0007']);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 2, 'unit_price' => 50_000]);
    InvoiceItem::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => 25_000]);
    $account = Account::factory()->for($invoice->book)->create(['initial_balance' => 0]);

    app(MarkInvoiceAsPaid::class)->handle($invoice, $account, today());

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->transaction)
        ->type->toBe(TransactionType::Income)
        ->amount->toBe('125000.00')
        ->description->toContain('INV-2026-0007')
        ->and($account->fresh()->current_balance)->toBe('125000.00');
});

it('rejects paying an invoice that is still a draft', function () {
    $invoice = Invoice::factory()->create();
    $account = Account::factory()->for($invoice->book)->create();

    app(MarkInvoiceAsPaid::class)->handle($invoice, $account, today());
})->throws(InvalidArgumentException::class);

it('rejects an account from another book', function () {
    $invoice = Invoice::factory()->sent()->create();
    $foreignAccount = Account::factory()->create();

    expect(fn () => app(MarkInvoiceAsPaid::class)->handle($invoice, $foreignAccount, today()))
        ->toThrow(InvalidArgumentException::class);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($foreignAccount->transactions()->count())->toBe(0);
});
