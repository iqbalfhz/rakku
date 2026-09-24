<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\DeviceReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus seluruh salinan buku dari ponsel.
 *
 * Dipakai saat berpindah buku dan saat keluar dari akun, supaya isi buku
 * seseorang tidak tertinggal di layar ponsel yang lalu dipakai orang lain.
 * Pemanggilnya wajib memastikan dulu lewat PendingChanges bahwa tidak ada
 * catatan yang belum terkirim.
 */
class LedgerWiper
{
    public function wipe(): void
    {
        DB::transaction(function (): void {
            DebtPayment::query()->delete();
            Debt::query()->delete();
            InvoicePayment::query()->delete();
            InvoiceItem::query()->delete();
            Invoice::query()->delete();
            Client::query()->delete();
            RecurringTransaction::query()->delete();
            Budget::query()->delete();
            Transaction::query()->delete();
            Account::query()->delete();
            Category::query()->delete();

            // Laporan kerusakan yang tertinggal akan salah alamat kalau ponsel ini
            // dipakai akun lain; kehilangan satu laporan lebih baik daripada itu.
            DeviceReport::query()->delete();
        });
    }
}
