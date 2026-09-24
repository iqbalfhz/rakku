<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menulis invoice di dalam ponsel, tanpa perlu sinyal.
 *
 * Nomor invoice sengaja dikosongkan: yang membuatnya adalah server, supaya urutannya
 * tidak bentrok kalau satu buku dipakai dari beberapa perangkat.
 */
class InvoiceWriter
{
    public function __construct(private LedgerWriter $ledgerWriter) {}

    /**
     * @param  array{client_public_id: string, issue_date: string, due_date: string, notes?: string|null, items: list<array{description: string, quantity: float, unit_price: float}>}  $data
     */
    public function record(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $invoice = Invoice::query()->create([
                'public_id' => Str::lower((string) Str::ulid()),
                'client_public_id' => $data['client_public_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'is_dirty' => true,
                'is_deleted' => false,
            ]);

            $this->replaceItems($invoice, $data['items']);

            return $invoice;
        });
    }

    /**
     * @param  array{client_public_id: string, issue_date: string, due_date: string, notes?: string|null, items: list<array{description: string, quantity: float, unit_price: float}>}  $data
     */
    public function revise(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $invoice->update([
                'client_public_id' => $data['client_public_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
                'is_dirty' => true,
            ]);

            $this->replaceItems($invoice, $data['items']);

            return $invoice;
        });
    }

    /**
     * Invoice yang sudah lunas punya transaksi pasangannya, jadi tidak boleh dihapus.
     */
    public function remove(Invoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            return false;
        }

        $invoice->update(['is_deleted' => true, 'is_dirty' => true]);

        return true;
    }

    /**
     * Lunasi invoice. Transaksinya dibuat sekaligus supaya saldo langsung berubah,
     * dengan id yang nanti dipakai server juga agar tidak lahir catatan kedua.
     */
    public function markAsPaid(Invoice $invoice, string $accountPublicId, string $paidAt): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $accountPublicId, $paidAt): InvoicePayment {
            $transactionPublicId = Str::lower((string) Str::ulid());

            $this->ledgerWriter->record([
                'public_id' => $transactionPublicId,
                'account_public_id' => $accountPublicId,
                'type' => 'income',
                'amount' => (float) $invoice->total_amount,
                'description' => "Pelunasan invoice {$invoice->numberLabel()} - ".($invoice->client->name ?? ''),
                'transaction_date' => $paidAt,
            ], queueForServer: false);

            $invoice->update(['status' => 'paid']);

            return InvoicePayment::query()->create([
                'invoice_public_id' => $invoice->public_id,
                'account_public_id' => $accountPublicId,
                'transaction_public_id' => $transactionPublicId,
                'paid_at' => $paidAt,
            ]);
        });
    }

    /**
     * Baris isi selalu ditulis ulang seluruhnya supaya yang dihapus benar-benar hilang.
     *
     * @param  list<array{description: string, quantity: float, unit_price: float}>  $items
     */
    private function replaceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $item) {
            $invoice->items()->create([
                'public_id' => Str::lower((string) Str::ulid()),
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
            ]);
        }

        $invoice->update([
            'total_amount' => collect($items)->sum(fn (array $item): float => round($item['quantity'] * $item['unit_price'], 2)),
        ]);
    }
}
