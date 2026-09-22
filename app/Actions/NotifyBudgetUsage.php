<?php

namespace App\Actions;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;

class NotifyBudgetUsage
{
    /**
     * Kirim notifikasi (premium) saat pengeluaran baru membuat budget kategori
     * melewati ambang peringatan atau melewati limitnya.
     */
    public function handle(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Expense || $transaction->category_id === null) {
            return;
        }

        $budget = Budget::query()
            ->with(['book.user', 'category'])
            ->where('category_id', $transaction->category_id)
            ->where('alert_enabled', true)
            ->first();

        if ($budget === null || ! $budget->book->user->isPremium()) {
            return;
        }

        $spentAfter = $budget->spentIn($transaction->transaction_date);
        $spentBefore = $spentAfter - (float) $transaction->amount;

        $notification = $this->buildNotification($budget, $spentBefore, $spentAfter);

        $notification?->sendToDatabase($budget->book->user);
    }

    private function buildNotification(Budget $budget, float $spentBefore, float $spentAfter): ?Notification
    {
        $limit = (float) $budget->amount;
        $threshold = $limit * ($budget->alert_threshold_percent ?? 100) / 100;
        $summary = sprintf(
            'Terpakai %s dari limit %s (%s%%) di buku %s.',
            Number::currency($spentAfter, 'IDR'),
            Number::currency($limit, 'IDR'),
            $budget->usagePercent($spentAfter),
            $budget->book->name,
        );

        if ($spentBefore < $limit && $spentAfter >= $limit) {
            return Notification::make()
                ->danger()
                ->title("Budget {$budget->category->name} terlampaui")
                ->body($summary);
        }

        if ($spentBefore < $threshold && $spentAfter >= $threshold) {
            return Notification::make()
                ->warning()
                ->title("Budget {$budget->category->name} hampir habis")
                ->body($summary);
        }

        return null;
    }
}
