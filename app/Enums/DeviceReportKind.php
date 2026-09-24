<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeviceReportKind: string implements HasColor, HasLabel
{
    case MigrationFailure = 'migration_failure';
    case Crash = 'crash';

    public function getLabel(): string
    {
        return match ($this) {
            self::MigrationFailure => 'Migrasi gagal',
            self::Crash => 'Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MigrationFailure => 'danger',
            self::Crash => 'warning',
        };
    }

    /**
     * Migrasi yang gagal membuat aplikasi tidak bisa dipakai sama sekali,
     * jadi admin dibangunkan; error biasa cukup menumpuk untuk dibaca nanti.
     */
    public function deservesAdminNotice(): bool
    {
        return $this === self::MigrationFailure;
    }
}
