<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Book;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterBook extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Buat buku baru';
    }

    /**
     * Buku kedua dan seterusnya hanya untuk pengguna premium.
     */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canCreateBook();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama buku')
                    ->placeholder("Contoh: Nadi's Fotocopy")
                    ->required()
                    ->maxLength(100),
            ]);
    }

    /**
     * @param  array{name: string}  $data
     */
    protected function handleRegistration(array $data): Book
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->books()->create($data);
    }
}
