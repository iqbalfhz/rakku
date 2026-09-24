<?php

namespace App\Support\Sync;

use App\Actions\MarkInvoiceAsPaid;
use App\Enums\AccountType;
use App\Enums\DebtStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin sinkronisasi antara buku di server dan salinannya di ponsel.
 *
 * Yang jumlahnya sedikit — akun, kategori, anggaran, klien, jadwal berulang — dikirim
 * utuh setiap kali, jadi yang hilang dari daftar memang sudah tidak ada. Yang bisa
 * menumpuk — transaksi, utang, invoice — dikirim sebagai selisih, lengkap dengan baris
 * yang sudah dihapus, supaya penghapusan ikut sampai ke ponsel.
 */
class LedgerSync
{
    /**
     * Berapa lama transaksi lama ikut dikirim saat ponsel menarik untuk pertama kali.
     */
    public const int INITIAL_MONTHS = 12;

    /**
     * @return array<string, mixed>
     */
    public function pull(Book $book, ?CarbonImmutable $since): array
    {
        $serverTime = CarbonImmutable::now();

        $isPremium = $book->user->isPremium();
        $debts = $isPremium ? $this->changedDebts($book, $since) : new Collection;

        return [
            'server_time' => $serverTime->utc()->toIso8601ZuluString(),
            'plan' => $this->planPayload($book->user),
            'accounts' => $book->accounts()->get()->map($this->accountPayload(...))->all(),
            'categories' => $book->categories()->get()->map($this->categoryPayload(...))->all(),
            'transactions' => $this->changedTransactions($book, $since)->map($this->transactionPayload(...))->all(),
            'budgets' => $book->budgets()->with('category')->get()->map($this->budgetPayload(...))->all(),
            'debts' => $debts->map($this->debtPayload(...))->all(),
            'debt_payments' => $this->changedPayments($book, $since, $debts)->map($this->paymentPayload(...))->all(),
            'clients' => $isPremium ? $book->clients()->get()->map($this->clientPayload(...))->all() : [],
            'invoices' => $isPremium ? $this->changedInvoices($book, $since)->map($this->invoicePayload(...))->all() : [],
            'recurring_transactions' => $isPremium
                ? $book->recurringTransactions()->with(['account', 'category'])->get()->map($this->recurringPayload(...))->all()
                : [],
        ];
    }

    /**
     * Terima perubahan dari ponsel. Baris dikenali lewat public_id yang dibuat ponsel,
     * dan yang menang adalah versi dengan updated_at paling baru.
     *
     * @param  list<array<string, mixed>>  $transactions
     * @param  list<array<string, mixed>>  $debts
     * @param  list<array<string, mixed>>  $debtPayments
     * @param  list<array<string, mixed>>  $budgets
     * @param  list<array<string, mixed>>  $invoices
     * @param  list<array<string, mixed>>  $invoicePayments
     * @param  list<array<string, mixed>>  $clients
     * @param  list<array<string, mixed>>  $recurringTransactions
     * @param  list<array<string, mixed>>  $accounts
     * @param  list<array<string, mixed>>  $categories
     * @return array{applied: int, skipped: int}
     */
    public function push(
        Book $book,
        array $transactions,
        array $debts = [],
        array $debtPayments = [],
        array $budgets = [],
        array $invoices = [],
        array $invoicePayments = [],
        array $clients = [],
        array $recurringTransactions = [],
        array $accounts = [],
        array $categories = [],
    ): array {
        $applied = 0;
        $skipped = 0;

        $isPremium = $book->user->isPremium();

        DB::transaction(function () use ($book, $transactions, $debts, $debtPayments, $budgets, $invoices, $invoicePayments, $clients, $recurringTransactions, $accounts, $categories, $isPremium, &$applied, &$skipped): void {
            // Akun dan kategori didahulukan: transaksi yang menyusul mungkin menunjuk ke yang baru dibuat.
            foreach ($accounts as $payload) {
                $this->applyIncomingAccount($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($categories as $payload) {
                $this->applyIncomingCategory($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($transactions as $payload) {
                $this->applyIncoming($book, $payload) ? $applied++ : $skipped++;
            }

            // Anggaran terbuka untuk semua; hanya peringatannya yang premium.
            foreach ($budgets as $payload) {
                $this->applyIncomingBudget($book, $payload, $isPremium) ? $applied++ : $skipped++;
            }

            // Utang-piutang dan invoice milik pelanggan premium; kiriman dari akun gratis dilewati begitu saja.
            if (! $isPremium) {
                $skipped += count($debts) + count($debtPayments) + count($invoices) + count($invoicePayments) + count($clients) + count($recurringTransactions);

                return;
            }

            foreach ($debts as $payload) {
                $this->applyIncomingDebt($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($debtPayments as $payload) {
                $this->applyIncomingPayment($book, $payload) ? $applied++ : $skipped++;
            }

            // Klien didahulukan karena invoice yang menyusul mungkin menunjuk ke klien baru ini.
            foreach ($clients as $payload) {
                $this->applyIncomingClient($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($invoices as $payload) {
                $this->applyIncomingInvoice($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($invoicePayments as $payload) {
                $this->applyIncomingInvoicePayment($book, $payload) ? $applied++ : $skipped++;
            }

            foreach ($recurringTransactions as $payload) {
                $this->applyIncomingRecurring($book, $payload) ? $applied++ : $skipped++;
            }
        });

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function changedTransactions(Book $book, ?CarbonImmutable $since): Collection
    {
        return $book->transactions()
            ->withTrashed()
            ->with(['account', 'category'])
            ->when(
                $since === null,
                fn (Builder $query) => $query->whereNull('deleted_at')
                    ->where('transaction_date', '>=', today()->subMonths(self::INITIAL_MONTHS)),
                fn (Builder $query) => $query->where('updated_at', '>', $this->localised($since)),
            )
            ->get();
    }

    /**
     * Pada tarikan pertama, utang yang belum lunas selalu ikut berapa pun umurnya
     * karena justru itulah yang masih harus ditagih atau dibayar.
     *
     * @return Collection<int, Debt>
     */
    private function changedDebts(Book $book, ?CarbonImmutable $since): Collection
    {
        return $book->debts()
            ->withTrashed()
            ->when(
                $since === null,
                fn (Builder $query) => $query->whereNull('deleted_at')
                    ->where(fn (Builder $nested) => $nested->where('status', DebtStatus::Unpaid)
                        ->orWhere('created_at', '>=', today()->subMonths(self::INITIAL_MONTHS))),
                fn (Builder $query) => $query->where('updated_at', '>', $this->localised($since)),
            )
            ->get();
    }

    /**
     * Cicilan mengikuti utangnya: pada tarikan pertama hanya cicilan milik utang yang ikut dikirim.
     *
     * @param  Collection<int, Debt>  $debts
     * @return Collection<int, DebtPayment>
     */
    private function changedPayments(Book $book, ?CarbonImmutable $since, Collection $debts): Collection
    {
        if ($since === null && $debts->isEmpty()) {
            return new Collection;
        }

        return DebtPayment::query()
            ->withTrashed()
            ->with([
                'debt' => fn ($relation) => $relation->withTrashed(),
                'account',
                'transaction' => fn ($relation) => $relation->withTrashed(),
            ])
            ->when(
                $since === null,
                fn (Builder $query) => $query->whereNull('deleted_at')->whereIn('debt_id', $debts->modelKeys()),
                fn (Builder $query) => $query->whereIn('debt_id', $book->debts()->withTrashed()->select('debts.id'))
                    ->where('debt_payments.updated_at', '>', $this->localised($since)),
            )
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyIncoming(Book $book, array $payload): bool
    {
        $existing = $book->transactions()->withTrashed()->where('public_id', $payload['public_id'])->first();

        if ($existing?->trashed()) {
            return false;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        if ($payload['is_deleted'] ?? false) {
            $existing?->delete();

            return $existing !== null;
        }

        $account = $book->accounts()->where('public_id', $payload['account_public_id'])->first();

        if ($account === null) {
            return false;
        }

        $attributes = [
            'account_id' => $account->id,
            'category_id' => $book->categories()->where('public_id', $payload['category_public_id'] ?? null)->value('id'),
            'type' => TransactionType::from($payload['type']),
            'amount' => $payload['amount'],
            'description' => $payload['description'] ?? null,
            'transaction_date' => $payload['transaction_date'],
        ];

        if ($existing === null) {
            $transaction = $book->transactions()->make($attributes);
            $transaction->public_id = $payload['public_id'];
            $transaction->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Pada tarikan pertama, invoice yang belum lunas selalu ikut berapa pun umurnya
     * karena itulah yang masih harus ditagih.
     *
     * @return Collection<int, Invoice>
     */
    private function changedInvoices(Book $book, ?CarbonImmutable $since): Collection
    {
        return $book->invoices()
            ->withTrashed()
            ->with(['client', 'items'])
            ->when(
                $since === null,
                fn (Builder $query) => $query->whereNull('deleted_at')
                    ->where(fn (Builder $nested) => $nested->whereNot('status', InvoiceStatus::Paid)
                        ->orWhere('issue_date', '>=', today()->subMonths(self::INITIAL_MONTHS))),
                fn (Builder $query) => $query->where('updated_at', '>', $this->localised($since)),
            )
            ->get();
    }

    /**
     * Akun yang sudah punya riwayat tidak boleh dihapus, aturan yang sama seperti di web:
     * saldo akun lain ikut bergantung padanya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingAccount(Book $book, array $payload): bool
    {
        $existing = $book->accounts()->where('public_id', $payload['public_id'])->first();

        if ($payload['is_deleted'] ?? false) {
            if ($existing === null || $existing->hasActivity()) {
                return false;
            }

            $existing->delete();

            return true;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        $attributes = [
            'name' => $payload['name'],
            'type' => AccountType::from($payload['type']),
            'initial_balance' => $payload['initial_balance'],
        ];

        if ($existing === null) {
            $account = $book->accounts()->make($attributes);
            $account->public_id = $payload['public_id'];
            $account->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Kategori yang dihapus melepaskan transaksinya menjadi tanpa kategori, bukan ikut menghapusnya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingCategory(Book $book, array $payload): bool
    {
        $existing = $book->categories()->where('public_id', $payload['public_id'])->first();

        if ($payload['is_deleted'] ?? false) {
            $existing?->delete();

            return $existing !== null;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        $attributes = ['name' => $payload['name'], 'type' => TransactionType::from($payload['type'])];

        if ($existing === null) {
            $category = $book->categories()->make($attributes);
            $category->public_id = $payload['public_id'];
            $category->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Jadwalnya boleh diatur dari ponsel, tapi yang menjalankannya tetap penjadwal
     * server — hanya itu yang bangun setiap hari tanpa ada yang membuka apa pun.
     * next_run_date sengaja tidak diterima dari ponsel supaya jadwal yang sudah
     * berjalan tidak mundur karena kiriman yang basi.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingRecurring(Book $book, array $payload): bool
    {
        $existing = $book->recurringTransactions()->where('public_id', $payload['public_id'])->first();

        if ($payload['is_deleted'] ?? false) {
            $existing?->delete();

            return $existing !== null;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        $account = $book->accounts()->where('public_id', $payload['account_public_id'])->first();

        if ($account === null) {
            return false;
        }

        $attributes = [
            'account_id' => $account->id,
            'category_id' => $book->categories()->where('public_id', $payload['category_public_id'] ?? null)->value('id'),
            'type' => TransactionType::from($payload['type']),
            'amount' => $payload['amount'],
            'description' => $payload['description'] ?? null,
            'frequency' => RecurringFrequency::from($payload['frequency']),
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'] ?? null,
            'is_active' => $payload['is_active'] ?? true,
        ];

        if ($existing === null) {
            $recurring = $book->recurringTransactions()->make($attributes);
            $recurring->public_id = $payload['public_id'];
            $recurring->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Klien baru bisa lahir di lapangan, saat invoice pertama untuknya dibuat.
     * Penghapusan sengaja tidak dilayani: invoice lama akan kehilangan pemiliknya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingClient(Book $book, array $payload): bool
    {
        $existing = $book->clients()->where('public_id', $payload['public_id'])->first();

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        $attributes = [
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'address' => $payload['address'] ?? null,
        ];

        if ($existing === null) {
            $client = $book->clients()->make($attributes);
            $client->public_id = $payload['public_id'];
            $client->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Nomor invoice tetap dibuat server supaya urutannya tidak bentrok antar perangkat.
     * Baris isinya diganti seluruhnya, karena ponsel selalu mengirim daftar yang utuh.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingInvoice(Book $book, array $payload): bool
    {
        $existing = $book->invoices()->withTrashed()->where('public_id', $payload['public_id'])->first();

        if ($existing?->trashed()) {
            return false;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        // Invoice yang sudah lunas punya transaksi pasangannya, jadi tidak boleh dihapus dari ponsel.
        if ($payload['is_deleted'] ?? false) {
            if ($existing === null || $existing->isPaid()) {
                return false;
            }

            $existing->delete();

            return true;
        }

        $client = $book->clients()->where('public_id', $payload['client_public_id'])->first();

        if ($client === null) {
            return false;
        }

        $attributes = [
            'client_id' => $client->id,
            'issue_date' => $payload['issue_date'],
            'due_date' => $payload['due_date'],
            'notes' => $payload['notes'] ?? null,
        ];

        if ($existing === null) {
            $invoice = $book->invoices()->make([...$attributes, 'invoice_number' => Invoice::nextNumberFor($book)]);
            $invoice->public_id = $payload['public_id'];
            $invoice->save();
        } else {
            $invoice = $existing->fill($attributes);
            $invoice->save();
        }

        $this->replaceInvoiceItems($invoice, $payload['items'] ?? []);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceInvoiceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->whereNotIn('public_id', array_column($items, 'public_id'))->delete();

        foreach ($items as $item) {
            $invoice->items()->updateOrCreate(
                ['public_id' => $item['public_id']],
                [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                ],
            );
        }
    }

    /**
     * Pelunasan invoice melahirkan transaksi, sama seperti cicilan utang, jadi
     * transaksinya memakai public_id buatan ponsel agar tidak tercatat dua kali.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingInvoicePayment(Book $book, array $payload): bool
    {
        $invoice = $book->invoices()->where('public_id', $payload['invoice_public_id'])->first();
        $account = $book->accounts()->where('public_id', $payload['account_public_id'])->first();

        if ($invoice === null || $account === null || ! $invoice->status->isPayable()) {
            return false;
        }

        $invoice = app(MarkInvoiceAsPaid::class)->handle($invoice, $account, CarbonImmutable::parse($payload['paid_at']));

        $transaction = $invoice->transaction;
        $publicId = $payload['transaction_public_id'] ?? null;

        if ($transaction !== null && $publicId !== null && ! Transaction::withTrashed()->where('public_id', $publicId)->exists()) {
            $transaction->forceFill(['public_id' => $publicId])->save();
        }

        return true;
    }

    /**
     * Satu kategori hanya boleh dibatasi satu anggaran, jadi kiriman yang bentrok
     * dengan anggaran lain untuk kategori yang sama ditolak, bukan menimpanya.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingBudget(Book $book, array $payload, bool $isPremium): bool
    {
        $existing = $book->budgets()->where('public_id', $payload['public_id'])->first();

        if ($payload['is_deleted'] ?? false) {
            $existing?->delete();

            return $existing !== null;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        $category = $book->categories()->where('public_id', $payload['category_public_id'])->first();

        if ($category === null) {
            return false;
        }

        $taken = $book->budgets()
            ->where('category_id', $category->id)
            ->where('public_id', '!=', $payload['public_id'])
            ->exists();

        if ($taken) {
            return false;
        }

        $attributes = ['category_id' => $category->id, 'amount' => $payload['amount']];

        if ($isPremium) {
            $attributes['alert_enabled'] = $payload['alert_enabled'] ?? false;
            $attributes['alert_threshold_percent'] = $payload['alert_threshold_percent'] ?? null;
        }

        if ($existing === null) {
            $budget = $book->budgets()->make($attributes);
            $budget->public_id = $payload['public_id'];
            $budget->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingDebt(Book $book, array $payload): bool
    {
        $existing = $book->debts()->withTrashed()->where('public_id', $payload['public_id'])->first();

        if ($existing?->trashed()) {
            return false;
        }

        $incomingUpdatedAt = CarbonImmutable::parse($payload['updated_at']);

        if ($existing !== null && $existing->updated_at->greaterThanOrEqualTo($incomingUpdatedAt)) {
            return false;
        }

        // Utang yang sudah punya cicilan tidak boleh dihapus, aturan yang sama seperti di web.
        if ($payload['is_deleted'] ?? false) {
            if ($existing === null || $existing->payments()->exists()) {
                return false;
            }

            $existing->delete();

            return true;
        }

        $attributes = [
            'type' => $payload['type'],
            'counterparty_name' => $payload['counterparty_name'],
            'amount' => $payload['amount'],
            'due_date' => $payload['due_date'] ?? null,
            'description' => $payload['description'] ?? null,
            'reminder_enabled' => $payload['reminder_enabled'] ?? true,
        ];

        if ($existing === null) {
            $debt = $book->debts()->make($attributes);
            $debt->public_id = $payload['public_id'];
            $debt->save();

            return true;
        }

        $existing->fill($attributes)->save();

        return true;
    }

    /**
     * Cicilan dari ponsel hanya bisa ditambah, tidak diubah.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyIncomingPayment(Book $book, array $payload): bool
    {
        if (DebtPayment::withTrashed()->where('public_id', $payload['public_id'])->exists()) {
            return false;
        }

        $debt = $book->debts()->where('public_id', $payload['debt_public_id'])->first();
        $account = $book->accounts()->where('public_id', $payload['account_public_id'])->first();

        if ($debt === null || $account === null) {
            return false;
        }

        $payment = $debt->payments()->make([
            'account_id' => $account->id,
            'amount' => $payload['amount'],
            'payment_date' => $payload['payment_date'],
            'notes' => $payload['notes'] ?? null,
        ]);
        $payment->public_id = $payload['public_id'];
        $payment->save();

        $this->adoptTransactionPublicId($payment, $payload['transaction_public_id'] ?? null);

        return true;
    }

    /**
     * Transaksi yang lahir dari cicilan memakai public_id buatan ponsel supaya tarikan
     * berikutnya mengenalinya sebagai baris yang sama, bukan catatan kedua.
     */
    private function adoptTransactionPublicId(DebtPayment $payment, ?string $publicId): void
    {
        $transaction = $payment->transaction;

        if ($publicId === null || $transaction === null) {
            return;
        }

        if (Transaction::withTrashed()->where('public_id', $publicId)->exists()) {
            return;
        }

        $transaction->forceFill(['public_id' => $publicId])->save();
    }

    /**
     * Waktu dari ponsel selalu UTC, sedangkan kolom di basis data mengikuti zona aplikasi.
     */
    private function localised(CarbonImmutable $since): CarbonImmutable
    {
        return $since->setTimezone(config('app.timezone'));
    }

    /**
     * Status langganan ikut dikirim supaya ponsel bisa mengunci fitur premium
     * sendiri, bahkan saat berhari-hari tanpa sinyal.
     *
     * @return array{is_premium: bool, expires_at: string|null}
     */
    private function planPayload(User $user): array
    {
        $subscription = $user->currentSubscription;

        return [
            'is_premium' => $user->isPremium(),
            'expires_at' => $subscription?->isActivePremium() && $subscription->expires_at !== null
                ? $subscription->expires_at->utc()->toIso8601ZuluString()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(Account $account): array
    {
        return [
            'public_id' => $account->public_id,
            'name' => $account->name,
            'type' => $account->type->value,
            'initial_balance' => (float) $account->initial_balance,
            'current_balance' => (float) $account->current_balance,
            'has_activity' => $account->hasActivity(),
            'updated_at' => $account->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category): array
    {
        return [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'type' => $category->type->value,
            'updated_at' => $category->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(Transaction $transaction): array
    {
        return [
            'public_id' => $transaction->public_id,
            'account_public_id' => $transaction->account?->public_id,
            'category_public_id' => $transaction->category?->public_id,
            'type' => $transaction->type->value,
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->toDateString(),
            'has_receipt' => $transaction->receipt_photo_path !== null,
            'is_deleted' => $transaction->trashed(),
            'updated_at' => $transaction->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recurringPayload(RecurringTransaction $recurring): array
    {
        return [
            'public_id' => $recurring->public_id,
            'account_public_id' => $recurring->account?->public_id,
            'category_public_id' => $recurring->category?->public_id,
            'type' => $recurring->type->value,
            'amount' => (float) $recurring->amount,
            'description' => $recurring->description,
            'frequency' => $recurring->frequency->value,
            'start_date' => $recurring->start_date->toDateString(),
            'next_run_date' => $recurring->next_run_date->toDateString(),
            'end_date' => $recurring->end_date?->toDateString(),
            'is_active' => (bool) $recurring->is_active,
            'updated_at' => $recurring->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(Client $client): array
    {
        return [
            'public_id' => $client->public_id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'public_id' => $invoice->public_id,
            'client_public_id' => $invoice->client?->public_id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'notes' => $invoice->notes,
            'status' => $invoice->status->value,
            'total_amount' => $invoice->totalAmount(),
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'public_id' => $item->public_id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])->all(),
            'is_deleted' => $invoice->trashed(),
            'updated_at' => $invoice->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetPayload(Budget $budget): array
    {
        return [
            'public_id' => $budget->public_id,
            'category_public_id' => $budget->category?->public_id,
            'amount' => (float) $budget->amount,
            'alert_enabled' => (bool) $budget->alert_enabled,
            'alert_threshold_percent' => $budget->alert_threshold_percent,
            'updated_at' => $budget->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function debtPayload(Debt $debt): array
    {
        return [
            'public_id' => $debt->public_id,
            'type' => $debt->type->value,
            'counterparty_name' => $debt->counterparty_name,
            'amount' => (float) $debt->amount,
            'remaining_amount' => (float) $debt->remaining_amount,
            'due_date' => $debt->due_date?->toDateString(),
            'description' => $debt->description,
            'status' => $debt->status->value,
            'reminder_enabled' => (bool) $debt->reminder_enabled,
            'is_deleted' => $debt->trashed(),
            'updated_at' => $debt->updated_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(DebtPayment $payment): array
    {
        return [
            'public_id' => $payment->public_id,
            'debt_public_id' => $payment->debt?->public_id,
            'account_public_id' => $payment->account?->public_id,
            'transaction_public_id' => $payment->transaction?->public_id,
            'amount' => (float) $payment->amount,
            'payment_date' => $payment->payment_date->toDateString(),
            'notes' => $payment->notes,
            'is_deleted' => $payment->trashed(),
            'updated_at' => $payment->updated_at->utc()->toIso8601ZuluString(),
        ];
    }
}
