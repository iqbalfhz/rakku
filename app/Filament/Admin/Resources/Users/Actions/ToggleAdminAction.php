<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Beri atau cabut akses panel admin; tidak tersedia untuk akun sendiri agar admin tidak terkunci.
 */
class ToggleAdminAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'toggleAdmin';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (User $record): string => $record->is_admin ? 'Cabut akses admin' : 'Jadikan admin')
            ->icon(fn (User $record): Heroicon => $record->is_admin ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShieldCheck)
            ->color(fn (User $record): string => $record->is_admin ? 'danger' : 'gray')
            ->hidden(fn (User $record): bool => $record->is(auth()->user()))
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => $record->is_admin
                ? "{$record->name} tidak akan bisa membuka panel admin lagi."
                : "{$record->name} akan bisa membuka panel admin dan mengatur premium semua pengguna.")
            ->action(function (User $record): void {
                $record->is_admin = ! $record->is_admin;
                $record->save();

                $this->successNotificationTitle($record->is_admin
                    ? "{$record->name} sekarang admin"
                    : "Akses admin {$record->name} dicabut");
                $this->success();
            });
    }
}
