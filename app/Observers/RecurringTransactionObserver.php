<?php

namespace App\Observers;

use App\Models\RecurringTransaction;

class RecurringTransactionObserver
{
    public function creating(RecurringTransaction $recurringTransaction): void
    {
        $recurringTransaction->next_run_date ??= $recurringTransaction->start_date;
    }
}
