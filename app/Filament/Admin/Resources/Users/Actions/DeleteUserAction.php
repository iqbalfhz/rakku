<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Actions\DeleteAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\In;

/**
 * Untuk permintaan hapus akun dari pengguna yang tidak bisa masuk sendiri,
 * dan untuk menutup akun yang menyalahgunakan layanan.
 */
class DeleteUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus pengguna')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (User $record): bool => $record->isNot(auth()->user()))
            ->requiresConfirmation()
            ->modalHeading('Hapus pengguna beserta seluruh datanya')
            ->modalDescription(fn (User $record): string => "Semua buku, transaksi, foto struk, invoice, dan tiket milik {$record->name} akan hilang permanen. Tindakan ini tidak bisa dibatalkan.")
            ->modalSubmitActionLabel('Hapus permanen')
            ->schema([
                TextInput::make('email')
                    ->label('Ketik email pengguna untuk memastikan')
                    ->placeholder(fn (User $record): string => $record->email)
                    ->required()
                    ->rule(fn (User $record): In => new In([$record->email]))
                    ->validationMessages(['in' => 'Email tidak cocok dengan pengguna yang dipilih.']),
            ])
            ->action(function (User $record): void {
                $name = $record->name;

                app(DeleteAccount::class)->handle($record);

                $this->successNotificationTitle("Akun {$name} sudah dihapus");
                $this->success();
            });
    }
}
