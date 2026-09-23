<?php

namespace App\Filament\Admin\Pages;

use App\Enums\SubscriptionPackage;
use App\Support\PublicContact;
use App\Support\SubscriptionConfig;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected string $view = 'filament.admin.pages.settings';

    protected static ?string $slug = 'pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cog6Tooth;

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'bank' => SubscriptionConfig::bank(),
            'prices' => SubscriptionConfig::prices(),
            'contact' => PublicContact::all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rekening tujuan transfer')
                    ->description('Ditampilkan di halaman Langganan yang dilihat pengguna.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('bank.name')
                            ->label('Nama bank')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('bank.account_number')
                            ->label('Nomor rekening')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('bank.account_holder')
                            ->label('Atas nama')
                            ->required()
                            ->maxLength(100),
                    ]),
                Section::make('Harga paket')
                    ->description('Perubahan harga hanya berlaku untuk pengajuan berikutnya, bukan yang sudah masuk.')
                    ->columns(3)
                    ->schema(array_map(
                        fn (SubscriptionPackage $package): TextInput => TextInput::make("prices.{$package->value}")
                            ->label("{$package->months()} bulan")
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(1000)
                            ->required(),
                        SubscriptionPackage::cases(),
                    )),
                Section::make('Kontak publik')
                    ->description('Dipajang di halaman depan untuk calon pengguna yang belum bisa membuka tiket bantuan. Kosongkan kalau tidak ingin ditampilkan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact.whatsapp')
                            ->label('Nomor WhatsApp')
                            ->placeholder('0812 3456 7890')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('contact.email')
                            ->label('Email')
                            ->placeholder('halo@rakku.test')
                            ->email()
                            ->maxLength(100),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SubscriptionConfig::save($data['bank'], $data['prices']);
        PublicContact::save($data['contact']);

        Notification::make()
            ->success()
            ->title('Pengaturan tersimpan')
            ->send();
    }
}
