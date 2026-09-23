<?php

namespace App\Filament\App\Pages\Auth;

use App\Filament\App\Actions\DeleteAccountAction;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * Profil bawaan Filament, ditambah cara pengguna menghapus akunnya sendiri.
 */
class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            Section::make('Hapus akun')
                ->description('Menutup akun dan menghapus seluruh catatan Anda dari RakKu.')
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
                ->schema([
                    Actions::make([
                        DeleteAccountAction::make(),
                    ])->key('delete-account'),
                ]),
        ]);
    }
}
