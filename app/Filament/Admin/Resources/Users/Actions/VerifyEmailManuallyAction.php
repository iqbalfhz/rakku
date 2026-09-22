<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Events\Verified;

/**
 * Buka akses pengguna yang email verifikasinya tidak sampai (mis. masuk spam atau SMTP bermasalah).
 */
class VerifyEmailManuallyAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'verifyEmailManually';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Verifikasi email manual')
            ->icon(Heroicon::OutlinedEnvelopeOpen)
            ->color('success')
            ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Pastikan {$record->email} memang milik {$record->name}. Setelah diverifikasi, pengguna bisa langsung masuk tanpa mengklik link di email.")
            ->action(function (User $record): void {
                $record->markEmailAsVerified();

                event(new Verified($record));

                $this->successNotificationTitle("Email {$record->email} terverifikasi");
                $this->success();
            });
    }
}
