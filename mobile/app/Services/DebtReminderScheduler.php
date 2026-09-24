<?php

namespace App\Services;

use App\Models\Debt;
use App\Support\Rupiah;
use Ikromjon\LocalNotifications\Facades\LocalNotifications;

/**
 * Menaruh pengingat jatuh tempo utang-piutang di dalam ponsel.
 *
 * Server mengirim pengingatnya lewat lonceng dan email, tapi itu hanya terbaca
 * kalau aplikasi dibuka. Pengingat di sini dipasang ke sistem ponsel, jadi tetap
 * berbunyi walau aplikasi tertutup dan tanpa sinyal sama sekali.
 */
class DebtReminderScheduler
{
    /**
     * Jumlah hari sebelum jatuh tempo saat pengingat berbunyi, disamakan dengan server.
     *
     * @var list<int>
     */
    public const array REMINDER_DAYS_BEFORE_DUE = [3, 1, 0];

    /**
     * Jam berapa pengingat berbunyi. Pagi, saat orang masih sempat menagih.
     */
    public const int REMINDER_HOUR = 8;

    public function __construct(private PlanGate $planGate) {}

    /**
     * Susun ulang seluruh pengingat dari awal.
     *
     * Dihapus semua dulu karena utang yang sudah lunas atau dihapus tidak punya
     * baris lagi untuk dicabut satu per satu. Utang adalah satu-satunya sumber
     * pengingat di aplikasi ini; kalau nanti ada yang lain, cabutnya harus per id.
     */
    public function refresh(): void
    {
        LocalNotifications::cancelAll();

        if (! $this->planGate->isPremium()) {
            return;
        }

        Debt::query()
            ->visible()
            ->unpaid()
            ->where('reminder_enabled', true)
            ->whereNotNull('due_date')
            ->get()
            ->each($this->scheduleFor(...));
    }

    /**
     * Minta izin memunculkan notifikasi. Android 13 ke atas menolak diam-diam tanpa ini.
     */
    public function requestPermission(): void
    {
        LocalNotifications::requestPermission();
    }

    private function scheduleFor(Debt $debt): void
    {
        foreach (self::REMINDER_DAYS_BEFORE_DUE as $days) {
            $firesAt = $debt->due_date->subDays($days)->setTime(self::REMINDER_HOUR, 0);

            // Yang waktunya sudah lewat tidak bisa dijadwalkan, dan memang tidak perlu.
            if ($firesAt->isPast()) {
                continue;
            }

            LocalNotifications::schedule([
                'id' => "debt-{$debt->public_id}-{$days}",
                'title' => $this->title($debt, $days),
                'body' => 'Sisa '.Rupiah::format((float) $debt->remaining_amount).'.',
                'at' => $firesAt->timestamp,
            ]);
        }
    }

    private function title(Debt $debt, int $days): string
    {
        $when = match ($days) {
            0 => 'hari ini',
            1 => 'besok',
            default => "{$days} hari lagi",
        };

        return "{$debt->summaryLabel()} jatuh tempo {$when}";
    }
}
