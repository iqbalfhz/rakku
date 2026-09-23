<?php

namespace App\Filament\Admin\Pages;

use App\Enums\SubscriptionPackage;
use App\Support\SubscriptionConfig;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SubscriptionSettings extends Page
{
    protected string $view = 'filament.admin.pages.subscription-settings';

    protected static ?string $slug = 'pengaturan-langganan';

    protected static ?string $title = 'Pengaturan Langganan';

    protected static ?string $navigationLabel = 'Pengaturan Langganan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cog6Tooth;

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'bank' => SubscriptionConfig::bank(),
            'prices' => SubscriptionConfig::prices(),
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SubscriptionConfig::save($data['bank'], $data['prices']);

        Notification::make()
            ->success()
            ->title('Pengaturan langganan tersimpan')
            ->send();
    }
}
