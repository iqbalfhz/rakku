<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;

/**
 * Berapa banyak yang ditulis di ponsel tapi belum sampai ke server.
 *
 * Angka ini menentukan tiga hal: apa yang ditampilkan di beranda, apakah aplikasi
 * boleh dibersihkan (ganti buku, keluar akun), dan apakah sinkron otomatis boleh
 * menunda diri. Selama masih ada isinya, tidak ada salinan catatan itu di mana pun.
 */
class PendingChanges
{
    public function count(): int
    {
        return Account::query()->pending()->count()
            + Category::query()->pending()->count()
            + Transaction::query()->pending()->count()
            + Debt::query()->pending()->count()
            + DebtPayment::query()->pending()->count()
            + Budget::query()->pending()->count()
            + Invoice::query()->pending()->count()
            + InvoicePayment::query()->count()
            + Client::query()->pending()->count()
            + RecurringTransaction::query()->pending()->count();
    }

    public function exist(): bool
    {
        return $this->count() > 0;
    }
}
