<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Menjembatani buku di server dengan salinannya di dalam ponsel.
 *
 * Urutannya selalu dorong dulu, baru tarik: catatan yang dibuat di ponsel harus
 * sampai ke server sebelum ponsel menerima gambaran terbaru, supaya tidak ada
 * yang tertimpa sebelum sempat terkirim.
 */
class SyncEngine
{
    public function __construct(
        private ApiClient $apiClient,
        private TokenStore $tokenStore,
        private PlanGate $planGate,
        private DebtReminderScheduler $reminderScheduler,
    ) {}

    public function sync(): void
    {
        $this->push();
        $this->uploadReceipts();
        $this->pull();
    }

    /**
     * Kirim foto struk yang masih menumpuk di ponsel, satu per satu.
     *
     * Kegagalan satu foto tidak menghentikan yang lain: yang gagal tetap
     * ditandai menunggu dan dicoba lagi pada sinkron berikutnya.
     */
    public function uploadReceipts(): void
    {
        $bookPublicId = $this->bookPublicId();

        Transaction::query()->withPendingReceipt()->get()->each(function (Transaction $transaction) use ($bookPublicId): void {
            $absolutePath = Storage::disk('local')->path($transaction->receipt_local_path);

            if (! is_file($absolutePath)) {
                $transaction->update(['receipt_local_path' => null]);

                return;
            }

            try {
                $this->apiClient->uploadReceipt($bookPublicId, $transaction->public_id, $absolutePath);
            } catch (Throwable) {
                return;
            }

            // Foto sudah aman di server, salinan di ponsel tidak perlu memakan ruang lagi.
            Storage::disk('local')->delete($transaction->receipt_local_path);

            $transaction->update(['has_receipt' => true, 'receipt_local_path' => null]);
        });
    }

    /**
     * Kirim catatan yang dibuat atau dihapus saat offline.
     */
    public function push(): void
    {
        $pending = Transaction::query()->pending()->get();
        $pendingDebts = Debt::query()->pending()->get();
        $pendingPayments = DebtPayment::query()->pending()->get();
        $pendingBudgets = Budget::query()->pending()->get();
        $pendingInvoices = Invoice::query()->pending()->with('items')->get();
        $pendingInvoicePayments = InvoicePayment::query()->get();
        $pendingClients = Client::query()->pending()->get();
        $pendingRecurring = RecurringTransaction::query()->pending()->get();
        $pendingAccounts = Account::query()->pending()->get();
        $pendingCategories = Category::query()->pending()->get();

        $changes = [
            'accounts' => $pendingAccounts->map($this->outgoingAccountPayload(...))->all(),
            'categories' => $pendingCategories->map($this->outgoingCategoryPayload(...))->all(),
            'clients' => $pendingClients->map($this->outgoingClientPayload(...))->all(),
            'transactions' => $pending->map($this->outgoingPayload(...))->all(),
            'debts' => $pendingDebts->map($this->outgoingDebtPayload(...))->all(),
            'debt_payments' => $pendingPayments->map($this->outgoingPaymentPayload(...))->all(),
            'budgets' => $pendingBudgets->map($this->outgoingBudgetPayload(...))->all(),
            'invoices' => $pendingInvoices->map($this->outgoingInvoicePayload(...))->all(),
            'invoice_payments' => $pendingInvoicePayments->map($this->outgoingInvoicePaymentPayload(...))->all(),
            'recurring_transactions' => $pendingRecurring->map($this->outgoingRecurringPayload(...))->all(),
        ];

        if (collect($changes)->every(fn (array $rows): bool => $rows === [])) {
            return;
        }

        $this->apiClient->push($this->bookPublicId(), $changes);

        DB::transaction(function () use ($pending, $pendingDebts, $pendingPayments, $pendingBudgets, $pendingInvoices, $pendingInvoicePayments, $pendingClients, $pendingRecurring, $pendingAccounts, $pendingCategories): void {
            Client::query()->whereIn('id', $pendingClients->pluck('id'))->update(['is_dirty' => false]);

            Account::query()->whereIn('id', $pendingAccounts->where('is_deleted', true)->pluck('id'))->delete();
            Account::query()->whereIn('id', $pendingAccounts->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            Category::query()->whereIn('id', $pendingCategories->where('is_deleted', true)->pluck('id'))->delete();
            Category::query()->whereIn('id', $pendingCategories->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            Transaction::query()->whereIn('id', $pending->where('is_deleted', true)->pluck('id'))->delete();
            Transaction::query()->whereIn('id', $pending->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            Debt::query()->whereIn('id', $pendingDebts->where('is_deleted', true)->pluck('id'))->delete();
            Debt::query()->whereIn('id', $pendingDebts->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            DebtPayment::query()->whereIn('id', $pendingPayments->pluck('id'))->update(['is_dirty' => false]);

            Budget::query()->whereIn('id', $pendingBudgets->where('is_deleted', true)->pluck('id'))->delete();
            Budget::query()->whereIn('id', $pendingBudgets->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            $removedInvoices = $pendingInvoices->where('is_deleted', true);
            InvoiceItem::query()->whereIn('invoice_public_id', $removedInvoices->pluck('public_id'))->delete();
            Invoice::query()->whereIn('id', $removedInvoices->pluck('id'))->delete();
            Invoice::query()->whereIn('id', $pendingInvoices->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);

            InvoicePayment::query()->whereIn('id', $pendingInvoicePayments->pluck('id'))->delete();

            RecurringTransaction::query()->whereIn('id', $pendingRecurring->where('is_deleted', true)->pluck('id'))->delete();
            RecurringTransaction::query()->whereIn('id', $pendingRecurring->where('is_deleted', false)->pluck('id'))->update(['is_dirty' => false]);
        });
    }

    /**
     * Tarik perubahan sejak sinkron terakhir.
     */
    public function pull(): void
    {
        $payload = $this->apiClient->pull($this->bookPublicId(), $this->tokenStore->lastSyncedAt());

        DB::transaction(function () use ($payload): void {
            $this->replaceAccounts($payload['accounts']);
            $this->replaceCategories($payload['categories']);
            $this->applyTransactions($payload['transactions']);

            // Server lama belum mengirim bagian ini; ponsel tidak boleh rusak karenanya.
            $this->replaceBudgets($payload['budgets'] ?? null);
            $this->applyDebts($payload['debts'] ?? []);
            $this->applyDebtPayments($payload['debt_payments'] ?? []);
            $this->replaceClients($payload['clients'] ?? null);
            $this->applyInvoices($payload['invoices'] ?? []);
            $this->replaceRecurring($payload['recurring_transactions'] ?? null);
        });

        if (isset($payload['plan'])) {
            $this->planGate->remember($payload['plan']);
        }

        $this->tokenStore->rememberSync($payload['server_time']);

        // Utang bisa berubah di web juga, jadi pengingat di ponsel ikut disusun ulang.
        $this->reminderScheduler->refresh();
    }

    private function bookPublicId(): string
    {
        $bookPublicId = $this->tokenStore->bookPublicId();

        if ($bookPublicId === null) {
            throw new RuntimeException('Belum ada buku yang dipilih.');
        }

        return $bookPublicId;
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingPayload(Transaction $transaction): array
    {
        return [
            'public_id' => $transaction->public_id,
            'account_public_id' => $transaction->account_public_id,
            'category_public_id' => $transaction->category_public_id,
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'is_deleted' => $transaction->is_deleted,
            'updated_at' => $transaction->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingDebtPayload(Debt $debt): array
    {
        return [
            'public_id' => $debt->public_id,
            'type' => $debt->type,
            'counterparty_name' => $debt->counterparty_name,
            'amount' => (float) $debt->amount,
            'due_date' => $debt->due_date?->toDateString(),
            'description' => $debt->description,
            'reminder_enabled' => $debt->reminder_enabled,
            'is_deleted' => $debt->is_deleted,
            'updated_at' => $debt->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingBudgetPayload(Budget $budget): array
    {
        return [
            'public_id' => $budget->public_id,
            'category_public_id' => $budget->category_public_id,
            'amount' => (float) $budget->amount,
            'alert_enabled' => $budget->alert_enabled,
            'alert_threshold_percent' => $budget->alert_threshold_percent,
            'is_deleted' => $budget->is_deleted,
            'updated_at' => $budget->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingPaymentPayload(DebtPayment $payment): array
    {
        return [
            'public_id' => $payment->public_id,
            'debt_public_id' => $payment->debt_public_id,
            'account_public_id' => $payment->account_public_id,
            'transaction_public_id' => $payment->transaction_public_id,
            'amount' => (float) $payment->amount,
            'payment_date' => $payment->payment_date->toDateString(),
            'notes' => $payment->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingInvoicePayload(Invoice $invoice): array
    {
        return [
            'public_id' => $invoice->public_id,
            'client_public_id' => $invoice->client_public_id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'notes' => $invoice->notes,
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'public_id' => $item->public_id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->all(),
            'is_deleted' => $invoice->is_deleted,
            'updated_at' => $invoice->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * current_balance tidak ikut dikirim: itu hasil hitungan server dari seluruh
     * transaksi, bukan angka yang boleh ditentukan ponsel.
     *
     * @return array<string, mixed>
     */
    private function outgoingAccountPayload(Account $account): array
    {
        return [
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type,
            'initial_balance' => (float) $account->initial_balance,
            'is_deleted' => $account->is_deleted,
            'updated_at' => $account->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingCategoryPayload(Category $category): array
    {
        return [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'type' => $category->type,
            'is_deleted' => $category->is_deleted,
            'updated_at' => $category->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * next_run_date tidak ikut dikirim: itu milik server, yang tahu sudah sampai
     * mana jadwal ini berjalan.
     *
     * @return array<string, mixed>
     */
    private function outgoingRecurringPayload(RecurringTransaction $recurring): array
    {
        return [
            'public_id' => $recurring->public_id,
            'account_public_id' => $recurring->account_public_id,
            'category_public_id' => $recurring->category_public_id,
            'type' => $recurring->type,
            'amount' => (float) $recurring->amount,
            'description' => $recurring->description,
            'frequency' => $recurring->frequency,
            'start_date' => $recurring->start_date->toDateString(),
            'end_date' => $recurring->end_date?->toDateString(),
            'is_active' => $recurring->is_active,
            'is_deleted' => $recurring->is_deleted,
            'updated_at' => $recurring->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingClientPayload(Client $client): array
    {
        return [
            'public_id' => $client->public_id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
            'updated_at' => $client->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingInvoicePaymentPayload(InvoicePayment $payment): array
    {
        return [
            'invoice_public_id' => $payment->invoice_public_id,
            'account_public_id' => $payment->account_public_id,
            'transaction_public_id' => $payment->transaction_public_id,
            'paid_at' => $payment->paid_at->toDateString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $accounts
     */
    private function replaceAccounts(array $accounts): void
    {
        foreach ($accounts as $account) {
            if (Account::query()->where('public_id', $account['public_id'])->value('is_dirty')) {
                continue;
            }

            Account::query()->updateOrCreate(
                ['public_id' => $account['public_id']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    // Server lama belum mengirim dua kolom ini.
                    'initial_balance' => $account['initial_balance'] ?? 0,
                    'current_balance' => $account['current_balance'],
                    'has_activity' => $account['has_activity'] ?? true,
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }

        // Yang baru dibuat di ponsel belum ada di daftar server, jadi tidak boleh ikut terhapus.
        Account::query()
            ->where('is_dirty', false)
            ->whereNotIn('public_id', array_column($accounts, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     */
    private function replaceCategories(array $categories): void
    {
        foreach ($categories as $category) {
            if (Category::query()->where('public_id', $category['public_id'])->value('is_dirty')) {
                continue;
            }

            Category::query()->updateOrCreate(
                ['public_id' => $category['public_id']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }

        Category::query()
            ->where('is_dirty', false)
            ->whereNotIn('public_id', array_column($categories, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    private function applyTransactions(array $transactions): void
    {
        foreach ($transactions as $transaction) {
            $local = Transaction::query()->where('public_id', $transaction['public_id'])->first();

            // Catatan yang belum terkirim tidak boleh ditimpa jawaban server:
            // isinya lebih baru daripada yang server tahu.
            if ($local?->is_dirty) {
                continue;
            }

            if ($transaction['is_deleted'] ?? false) {
                $local?->delete();

                continue;
            }

            Transaction::query()->updateOrCreate(
                ['public_id' => $transaction['public_id']],
                [
                    'account_public_id' => $transaction['account_public_id'],
                    'category_public_id' => $transaction['category_public_id'],
                    'type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'description' => $transaction['description'],
                    'transaction_date' => $transaction['transaction_date'],
                    'has_receipt' => $transaction['has_receipt'] ?? false,
                    'server_updated_at' => $transaction['updated_at'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }
    }

    /**
     * Daftar anggaran selalu dikirim utuh, jadi yang tidak ada di daftar memang sudah
     * dihapus di server. Yang belum sempat terkirim dari ponsel tetap dibiarkan hidup.
     *
     * @param  list<array<string, mixed>>|null  $budgets
     */
    private function replaceBudgets(?array $budgets): void
    {
        if ($budgets === null) {
            return;
        }

        foreach ($budgets as $budget) {
            $local = Budget::query()->where('public_id', $budget['public_id'])->first();

            if ($local?->is_dirty) {
                continue;
            }

            Budget::query()->updateOrCreate(
                ['public_id' => $budget['public_id']],
                [
                    'category_public_id' => $budget['category_public_id'],
                    'amount' => $budget['amount'],
                    'alert_enabled' => $budget['alert_enabled'],
                    'alert_threshold_percent' => $budget['alert_threshold_percent'],
                    'server_updated_at' => $budget['updated_at'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }

        Budget::query()
            ->where('is_dirty', false)
            ->whereNotIn('public_id', array_column($budgets, 'public_id'))
            ->delete();
    }

    /**
     * Daftar jadwal selalu dikirim utuh, jadi yang tidak ada di daftar memang sudah
     * dihapus di server. Yang belum sempat terkirim dari ponsel tetap dibiarkan hidup.
     *
     * @param  list<array<string, mixed>>|null  $schedules
     */
    private function replaceRecurring(?array $schedules): void
    {
        if ($schedules === null) {
            return;
        }

        foreach ($schedules as $schedule) {
            if (RecurringTransaction::query()->where('public_id', $schedule['public_id'])->value('is_dirty')) {
                continue;
            }

            RecurringTransaction::query()->updateOrCreate(
                ['public_id' => $schedule['public_id']],
                [
                    'account_public_id' => $schedule['account_public_id'],
                    'category_public_id' => $schedule['category_public_id'],
                    'type' => $schedule['type'],
                    'amount' => $schedule['amount'],
                    'description' => $schedule['description'],
                    'frequency' => $schedule['frequency'],
                    'start_date' => $schedule['start_date'],
                    'next_run_date' => $schedule['next_run_date'],
                    'end_date' => $schedule['end_date'],
                    'is_active' => $schedule['is_active'],
                    'server_updated_at' => $schedule['updated_at'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }

        RecurringTransaction::query()
            ->where('is_dirty', false)
            ->whereNotIn('public_id', array_column($schedules, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>|null  $clients
     */
    private function replaceClients(?array $clients): void
    {
        if ($clients === null) {
            return;
        }

        foreach ($clients as $client) {
            if (Client::query()->where('public_id', $client['public_id'])->value('is_dirty')) {
                continue;
            }

            Client::query()->updateOrCreate(
                ['public_id' => $client['public_id']],
                [
                    'name' => $client['name'],
                    'email' => $client['email'],
                    'phone' => $client['phone'],
                    'address' => $client['address'],
                    'is_dirty' => false,
                ],
            );
        }

        // Klien yang baru dibuat di ponsel belum ada di daftar server, jadi tidak boleh ikut terhapus.
        Client::query()
            ->where('is_dirty', false)
            ->whereNotIn('public_id', array_column($clients, 'public_id'))
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     */
    private function applyInvoices(array $invoices): void
    {
        foreach ($invoices as $invoice) {
            $local = Invoice::query()->where('public_id', $invoice['public_id'])->first();

            // Pelunasan yang belum terkirim lebih baru daripada yang server tahu.
            $awaitingPayment = InvoicePayment::query()->where('invoice_public_id', $invoice['public_id'])->exists();

            if ($local?->is_dirty || $awaitingPayment) {
                continue;
            }

            if ($invoice['is_deleted'] ?? false) {
                InvoiceItem::query()->where('invoice_public_id', $invoice['public_id'])->delete();
                $local?->delete();

                continue;
            }

            $stored = Invoice::query()->updateOrCreate(
                ['public_id' => $invoice['public_id']],
                [
                    'client_public_id' => $invoice['client_public_id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'issue_date' => $invoice['issue_date'],
                    'due_date' => $invoice['due_date'],
                    'notes' => $invoice['notes'],
                    'status' => $invoice['status'],
                    'total_amount' => $invoice['total_amount'],
                    'server_updated_at' => $invoice['updated_at'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );

            $stored->items()->delete();

            foreach ($invoice['items'] ?? [] as $item) {
                $stored->items()->create([
                    'public_id' => $item['public_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $debts
     */
    private function applyDebts(array $debts): void
    {
        foreach ($debts as $debt) {
            $local = Debt::query()->where('public_id', $debt['public_id'])->first();

            if ($local?->is_dirty) {
                continue;
            }

            // Utang yang hilang di server membawa serta daftar cicilannya.
            if ($debt['is_deleted'] ?? false) {
                DebtPayment::query()->where('debt_public_id', $debt['public_id'])->delete();
                $local?->delete();

                continue;
            }

            Debt::query()->updateOrCreate(
                ['public_id' => $debt['public_id']],
                [
                    'type' => $debt['type'],
                    'counterparty_name' => $debt['counterparty_name'],
                    'amount' => $debt['amount'],
                    'remaining_amount' => $debt['remaining_amount'],
                    'due_date' => $debt['due_date'],
                    'description' => $debt['description'],
                    'status' => $debt['status'],
                    'reminder_enabled' => $debt['reminder_enabled'],
                    'server_updated_at' => $debt['updated_at'],
                    'is_dirty' => false,
                    'is_deleted' => false,
                ],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function applyDebtPayments(array $payments): void
    {
        foreach ($payments as $payment) {
            $local = DebtPayment::query()->where('public_id', $payment['public_id'])->first();

            if ($local?->is_dirty) {
                continue;
            }

            if ($payment['is_deleted'] ?? false) {
                $local?->delete();

                continue;
            }

            DebtPayment::query()->updateOrCreate(
                ['public_id' => $payment['public_id']],
                [
                    'debt_public_id' => $payment['debt_public_id'],
                    'account_public_id' => $payment['account_public_id'],
                    'transaction_public_id' => $payment['transaction_public_id'],
                    'amount' => $payment['amount'],
                    'payment_date' => $payment['payment_date'],
                    'notes' => $payment['notes'],
                    'server_updated_at' => $payment['updated_at'],
                    'is_dirty' => false,
                ],
            );
        }
    }
}
