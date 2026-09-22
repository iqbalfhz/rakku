<?php

namespace App\Observers;

use App\Models\Account;

class AccountObserver
{
    public function creating(Account $account): void
    {
        $account->current_balance = $account->initial_balance ?? 0;
    }

    /**
     * Perubahan saldo awal ikut menggeser saldo berjalan sebesar selisihnya.
     */
    public function updated(Account $account): void
    {
        if (! $account->wasChanged('initial_balance')) {
            return;
        }

        Account::adjustBalance(
            $account->id,
            (float) $account->initial_balance - (float) $account->getOriginal('initial_balance'),
        );
    }
}
