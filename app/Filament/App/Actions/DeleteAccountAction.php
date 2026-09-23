<?php

namespace App\Filament\App\Actions;

use App\Actions\DeleteAccount;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\In;

class DeleteAccountAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteAccount';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Hapus akun saya')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus akun beserta seluruh isinya')
            ->modalDescription('Semua buku, transaksi, foto struk, invoice, dan tiket bantuan Anda akan hilang permanen. Tindakan ini tidak bisa dibatalkan, dan kami tidak bisa memulihkannya. Ekspor data Anda dulu kalau masih dibutuhkan.')
            ->modalSubmitActionLabel('Hapus permanen')
            ->schema([
                TextInput::make('email')
                    ->label('Ketik email Anda untuk memastikan')
                    ->placeholder(fn (): string => Auth::user()->email)
                    ->required()
                    ->rule(fn (): In => new In([Auth::user()->email]))
                    ->validationMessages(['in' => 'Email tidak cocok dengan akun yang sedang masuk.']),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->required()
                    ->currentPassword()
                    ->validationMessages(['current_password' => 'Kata sandi salah.']),
            ])
            ->action(function (): void {
                /** @var User $user */
                $user = Auth::user();

                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                app(DeleteAccount::class)->handle($user);

                Notification::make()
                    ->success()
                    ->title('Akun Anda sudah dihapus')
                    ->send();

                redirect()->route('landing');
            });
    }
}
