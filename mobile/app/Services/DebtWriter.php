<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menulis utang-piutang di dalam ponsel, tanpa perlu sinyal.
 *
 * Sisa utang dihitung ulang di sini supaya angka di layar langsung benar.
 * Setelah sinkron, hitungan dari server yang berlaku.
 */
class DebtWriter
{
    public function __construct(
        private LedgerWriter $ledgerWriter,
        private DebtReminderScheduler $reminderScheduler,
    ) {}

    /**
     * @param  array{type: string, counterparty_name: string, amount: float, due_date?: string|null, description?: string|null, reminder_enabled?: bool}  $data
     */
    public function record(array $data): Debt
    {
        $debt = Debt::query()->create([
            'public_id' => Str::lower((string) Str::ulid()),
            'type' => $data['type'],
            'counterparty_name' => $data['counterparty_name'],
            'amount' => $data['amount'],
            'remaining_amount' => $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'unpaid',
            'reminder_enabled' => $data['reminder_enabled'] ?? true,
            'is_dirty' => true,
            'is_deleted' => false,
        ]);

        $this->reminderScheduler->refresh();

        return $debt;
    }

    /**
     * @param  array{type: string, counterparty_name: string, amount: float, due_date?: string|null, description?: string|null, reminder_enabled?: bool}  $data
     */
    public function revise(Debt $debt, array $data): Debt
    {
        return DB::transaction(function () use ($debt, $data): Debt {
            $debt->update([
                'type' => $data['type'],
                'counterparty_name' => $data['counterparty_name'],
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'reminder_enabled' => $data['reminder_enabled'] ?? true,
                'is_dirty' => true,
            ]);

            $this->recalculate($debt);
            $this->reminderScheduler->refresh();

            return $debt;
        });
    }

    /**
     * Utang yang sudah dicicil tidak boleh dihapus, karena transaksi cicilannya
     * sudah terlanjur ikut menghitung saldo. Server memakai aturan yang sama.
     */
    public function remove(Debt $debt): bool
    {
        if ($debt->payments()->exists()) {
            return false;
        }

        $debt->update(['is_deleted' => true, 'is_dirty' => true]);

        $this->reminderScheduler->refresh();

        return true;
    }

    /**
     * Catat cicilan. Transaksinya dibuat sekaligus supaya saldo akun langsung berubah,
     * dengan id yang nanti dipakai server juga agar tidak lahir catatan kedua.
     *
     * @param  array{account_public_id: string, amount: float, payment_date: string, notes?: string|null}  $data
     */
    public function pay(Debt $debt, array $data): DebtPayment
    {
        return DB::transaction(function () use ($debt, $data): DebtPayment {
            $transactionPublicId = Str::lower((string) Str::ulid());

            $this->ledgerWriter->record([
                'public_id' => $transactionPublicId,
                'account_public_id' => $data['account_public_id'],
                'type' => $debt->isReceivable() ? 'income' : 'expense',
                'amount' => $data['amount'],
                'description' => "Pembayaran {$debt->typeLabel()}: {$debt->counterparty_name}",
                'transaction_date' => $data['payment_date'],
            ], queueForServer: false);

            $payment = DebtPayment::query()->create([
                'public_id' => Str::lower((string) Str::ulid()),
                'debt_public_id' => $debt->public_id,
                'account_public_id' => $data['account_public_id'],
                'transaction_public_id' => $transactionPublicId,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'is_dirty' => true,
            ]);

            $this->recalculate($debt);
            $this->reminderScheduler->refresh();

            return $payment;
        });
    }

    /**
     * Sisa dan status dihitung dari cicilan yang sudah tercatat, bukan disimpan sendiri.
     */
    private function recalculate(Debt $debt): void
    {
        $paidAmount = (float) $debt->payments()->sum('amount');
        $remainingAmount = max(0, (float) $debt->amount - $paidAmount);

        $debt->update([
            'remaining_amount' => $remainingAmount,
            'status' => $remainingAmount > 0 ? 'unpaid' : 'paid',
        ]);
    }
}
