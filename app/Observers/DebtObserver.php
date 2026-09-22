<?php

namespace App\Observers;

use App\Models\Debt;

class DebtObserver
{
    public function saving(Debt $debt): void
    {
        $debt->recalculateRemainingAmount();
    }
}
