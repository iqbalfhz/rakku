<?php

namespace App\Observers;

use App\Enums\SubscriptionPlan;
use App\Models\Book;
use App\Models\User;

class UserObserver
{
    /**
     * User baru otomatis mendapat plan free dan buku default.
     */
    public function created(User $user): void
    {
        $user->subscribeTo(SubscriptionPlan::Free);

        $user->books()->create([
            'name' => Book::DEFAULT_NAME,
            'is_default' => true,
        ]);
    }
}
