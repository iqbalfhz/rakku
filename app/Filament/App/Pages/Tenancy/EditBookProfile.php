<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Book;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

/**
 * @property Book $tenant
 */
class EditBookProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Pengaturan buku';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama buku')
                    ->required()
                    ->maxLength(100),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus buku')
                ->record($this->tenant)
                ->hidden($this->tenant->is_default)
                ->modalDescription('Semua akun, transaksi, budget, utang-piutang, dan invoice di buku ini akan terhapus permanen.')
                ->successRedirectUrl(fn (): string => Filament::getCurrentPanel()->getUrl()),
        ];
    }
}
