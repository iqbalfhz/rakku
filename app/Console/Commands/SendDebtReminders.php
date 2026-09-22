<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Support\Rupiah;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('app:send-debt-reminders')]
#[Description('Kirim pengingat utang-piutang yang mendekati jatuh tempo (khusus pengguna premium)')]
class SendDebtReminders extends Command
{
    /**
     * Jumlah hari sebelum jatuh tempo saat pengingat dikirim.
     *
     * @var list<int>
     */
    public const array REMINDER_DAYS_BEFORE_DUE = [3, 1, 0];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sentCount = 0;

        Debt::query()
            ->unpaid()
            ->where('reminder_enabled', true)
            ->where(function (Builder $query): void {
                foreach (self::REMINDER_DAYS_BEFORE_DUE as $days) {
                    $query->orWhereDate('due_date', today()->addDays($days));
                }
            })
            ->with('book.user.currentSubscription')
            ->each(function (Debt $debt) use (&$sentCount): void {
                if (! $debt->book->user->isPremium()) {
                    return;
                }

                Notification::make()
                    ->warning()
                    ->title("{$debt->summaryLabel()} jatuh tempo {$this->dueLabel($debt)}")
                    ->body(sprintf(
                        'Sisa %s di buku %s.',
                        Rupiah::format((float) $debt->remaining_amount),
                        $debt->book->name,
                    ))
                    ->sendToDatabase($debt->book->user);

                $sentCount++;
            });

        $this->info("{$sentCount} pengingat utang-piutang terkirim.");

        return self::SUCCESS;
    }

    private function dueLabel(Debt $debt): string
    {
        $daysLeft = (int) today()->diffInDays($debt->due_date);

        return match ($daysLeft) {
            0 => 'hari ini',
            1 => 'besok',
            default => "{$daysLeft} hari lagi",
        };
    }
}
