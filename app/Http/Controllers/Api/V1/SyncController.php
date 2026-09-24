<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Support\Sync\LedgerSync;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu buku, dua arah: ponsel menarik perubahan server dan mendorong catatan yang dibuat offline.
 */
class SyncController extends Controller
{
    public function __construct(private LedgerSync $ledgerSync) {}

    public function show(Request $request, Book $book): JsonResponse
    {
        $this->authorizeBook($request, $book);

        $request->validate(['since' => ['nullable', 'date']]);

        $since = $request->filled('since') ? CarbonImmutable::parse($request->string('since')->toString()) : null;

        return response()->json($this->ledgerSync->pull($book, $since));
    }

    public function store(Request $request, Book $book): JsonResponse
    {
        $this->authorizeBook($request, $book);

        $data = $request->validate([
            'transactions' => ['present', 'array'],
            'transactions.*.public_id' => ['required', 'ulid'],
            'transactions.*.account_public_id' => ['required', 'ulid'],
            'transactions.*.category_public_id' => ['nullable', 'ulid'],
            'transactions.*.type' => ['required', 'in:income,expense'],
            'transactions.*.amount' => ['required', 'numeric', 'min:0'],
            'transactions.*.description' => ['nullable', 'string', 'max:255'],
            'transactions.*.transaction_date' => ['required', 'date'],
            'transactions.*.is_deleted' => ['boolean'],
            'transactions.*.updated_at' => ['required', 'date'],
            'debts' => ['sometimes', 'array'],
            'debts.*.public_id' => ['required', 'ulid'],
            'debts.*.type' => ['required', 'in:receivable,payable'],
            'debts.*.counterparty_name' => ['required', 'string', 'max:255'],
            'debts.*.amount' => ['required', 'numeric', 'min:0'],
            'debts.*.due_date' => ['nullable', 'date'],
            'debts.*.description' => ['nullable', 'string', 'max:1000'],
            'debts.*.reminder_enabled' => ['boolean'],
            'debts.*.is_deleted' => ['boolean'],
            'debts.*.updated_at' => ['required', 'date'],
            'debt_payments' => ['sometimes', 'array'],
            'debt_payments.*.public_id' => ['required', 'ulid'],
            'debt_payments.*.debt_public_id' => ['required', 'ulid'],
            'debt_payments.*.account_public_id' => ['required', 'ulid'],
            'debt_payments.*.transaction_public_id' => ['nullable', 'ulid'],
            'debt_payments.*.amount' => ['required', 'numeric', 'min:1'],
            'debt_payments.*.payment_date' => ['required', 'date'],
            'debt_payments.*.notes' => ['nullable', 'string', 'max:1000'],
            'budgets' => ['sometimes', 'array'],
            'budgets.*.public_id' => ['required', 'ulid'],
            'budgets.*.category_public_id' => ['required', 'ulid'],
            'budgets.*.amount' => ['required', 'numeric', 'min:1'],
            'budgets.*.alert_enabled' => ['boolean'],
            'budgets.*.alert_threshold_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'budgets.*.is_deleted' => ['boolean'],
            'budgets.*.updated_at' => ['required', 'date'],
            'invoices' => ['sometimes', 'array'],
            'invoices.*.public_id' => ['required', 'ulid'],
            'invoices.*.client_public_id' => ['required', 'ulid'],
            'invoices.*.issue_date' => ['required', 'date'],
            'invoices.*.due_date' => ['required', 'date'],
            'invoices.*.notes' => ['nullable', 'string', 'max:1000'],
            'invoices.*.items' => ['present', 'array'],
            'invoices.*.items.*.public_id' => ['required', 'ulid'],
            'invoices.*.items.*.description' => ['required', 'string', 'max:255'],
            'invoices.*.items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'invoices.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'invoices.*.is_deleted' => ['boolean'],
            'invoices.*.updated_at' => ['required', 'date'],
            'invoice_payments' => ['sometimes', 'array'],
            'invoice_payments.*.invoice_public_id' => ['required', 'ulid'],
            'invoice_payments.*.account_public_id' => ['required', 'ulid'],
            'invoice_payments.*.transaction_public_id' => ['nullable', 'ulid'],
            'invoice_payments.*.paid_at' => ['required', 'date'],
            'clients' => ['sometimes', 'array'],
            'clients.*.public_id' => ['required', 'ulid'],
            'clients.*.name' => ['required', 'string', 'max:255'],
            'clients.*.email' => ['nullable', 'email', 'max:255'],
            'clients.*.phone' => ['nullable', 'string', 'max:30'],
            'clients.*.address' => ['nullable', 'string', 'max:500'],
            'clients.*.updated_at' => ['required', 'date'],
            'recurring_transactions' => ['sometimes', 'array'],
            'recurring_transactions.*.public_id' => ['required', 'ulid'],
            'recurring_transactions.*.account_public_id' => ['required', 'ulid'],
            'recurring_transactions.*.category_public_id' => ['nullable', 'ulid'],
            'recurring_transactions.*.type' => ['required', 'in:income,expense'],
            'recurring_transactions.*.amount' => ['required', 'numeric', 'min:1'],
            'recurring_transactions.*.description' => ['nullable', 'string', 'max:1000'],
            'recurring_transactions.*.frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'recurring_transactions.*.start_date' => ['required', 'date'],
            'recurring_transactions.*.end_date' => ['nullable', 'date', 'after_or_equal:recurring_transactions.*.start_date'],
            'recurring_transactions.*.is_active' => ['boolean'],
            'recurring_transactions.*.is_deleted' => ['boolean'],
            'recurring_transactions.*.updated_at' => ['required', 'date'],
            'accounts' => ['sometimes', 'array'],
            'accounts.*.public_id' => ['required', 'ulid'],
            'accounts.*.name' => ['required', 'string', 'max:255'],
            'accounts.*.type' => ['required', 'in:cash,bank,e-wallet,other'],
            'accounts.*.initial_balance' => ['required', 'numeric'],
            'accounts.*.is_deleted' => ['boolean'],
            'accounts.*.updated_at' => ['required', 'date'],
            'categories' => ['sometimes', 'array'],
            'categories.*.public_id' => ['required', 'ulid'],
            'categories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.type' => ['required', 'in:income,expense'],
            'categories.*.is_deleted' => ['boolean'],
            'categories.*.updated_at' => ['required', 'date'],
            'transfers' => ['sometimes', 'array'],
            'transfers.*.public_id' => ['required', 'ulid'],
            'transfers.*.from_account_public_id' => ['required', 'ulid'],
            'transfers.*.to_account_public_id' => ['required', 'ulid', 'different:transfers.*.from_account_public_id'],
            'transfers.*.amount' => ['required', 'numeric', 'min:1'],
            'transfers.*.description' => ['nullable', 'string', 'max:255'],
            'transfers.*.transfer_date' => ['required', 'date'],
            'transfers.*.is_deleted' => ['boolean'],
            'transfers.*.updated_at' => ['required', 'date'],
        ]);

        $result = $this->ledgerSync->push(
            $book,
            $data['transactions'],
            $data['debts'] ?? [],
            $data['debt_payments'] ?? [],
            $data['budgets'] ?? [],
            $data['invoices'] ?? [],
            $data['invoice_payments'] ?? [],
            $data['clients'] ?? [],
            $data['recurring_transactions'] ?? [],
            $data['accounts'] ?? [],
            $data['categories'] ?? [],
            $data['transfers'] ?? [],
        );

        return response()->json($result + ['server_time' => now()->utc()->toIso8601ZuluString()]);
    }

    private function authorizeBook(Request $request, Book $book): void
    {
        abort_unless($book->user_id === $request->user()->id, 404);
    }
}
