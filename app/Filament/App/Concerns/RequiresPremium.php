<?php

namespace App\Filament\App\Concerns;

use App\Models\User;

/**
 * Batasi resource/page hanya untuk pengguna dengan plan premium aktif.
 */
trait RequiresPremium
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isPremium();
    }
}
