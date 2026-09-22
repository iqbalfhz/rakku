<?php

namespace App\Filament\App\Pages;

use App\Enums\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\SubscriptionConfig;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class Subscription extends Page
{
    protected string $view = 'filament.app.pages.subscription';

    protected static ?string $slug = 'langganan';

    protected static ?string $title = 'Langganan';

    protected static ?string $navigationLabel = 'Langganan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Sparkles;

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Radio::make('package')
                    ->label('Pilih paket')
                    ->options(SubscriptionPackage::class)
                    ->default(SubscriptionPackage::Monthly)
                    ->required(),
                FileUpload::make('proof_path')
                    ->label('Bukti transfer')
                    ->helperText('Foto atau tangkapan layar struk transfer, maksimal 5 MB.')
                    ->image()
                    ->disk(SubscriptionPayment::proofDisk())
                    ->directory(SubscriptionPayment::PROOF_DIRECTORY)
                    ->visibility('private')
                    ->maxSize(5120)
                    ->openable()
                    ->required(),
                Textarea::make('note')
                    ->label('Catatan (opsional)')
                    ->placeholder('Misalnya nama pengirim, kalau berbeda dengan nama akun Anda.')
                    ->maxLength(500),
            ])
            ->statePath('data');
    }

    /**
     * Simpan pengajuan lalu beri tahu admin agar segera diverifikasi.
     */
    public function submit(): void
    {
        $data = $this->form->getState();

        /** @var SubscriptionPackage $package */
        $package = $data['package'];

        $payment = $this->user()->subscriptionPayments()->create([
            'package' => $package,
            'amount' => $package->price(),
            'proof_path' => $data['proof_path'],
            'note' => $data['note'] ?? null,
        ]);

        $this->notifyAdmins($payment);

        $this->form->fill();

        Notification::make()
            ->success()
            ->title('Bukti transfer terkirim')
            ->body('Admin akan memverifikasi pembayaran Anda. Anda akan diberi tahu di aplikasi ini begitu premium aktif.')
            ->send();
    }

    public function pendingPayment(): ?SubscriptionPayment
    {
        return $this->user()->subscriptionPayments()->pending()->latest()->first();
    }

    /**
     * @return Collection<int, SubscriptionPayment>
     */
    public function paymentHistory(): Collection
    {
        return $this->user()->subscriptionPayments()->latest()->limit(10)->get();
    }

    public function premiumExpiresAt(): ?string
    {
        $subscription = $this->user()->currentSubscription;

        return $subscription?->isActivePremium() && $subscription->expires_at !== null
            ? $subscription->expires_at->translatedFormat('j F Y')
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function bankAccount(): array
    {
        return SubscriptionConfig::bank();
    }

    /**
     * @return array<int, SubscriptionPackage>
     */
    public function packages(): array
    {
        return SubscriptionPackage::cases();
    }

    public function user(): User
    {
        return auth()->user();
    }

    private function notifyAdmins(SubscriptionPayment $payment): void
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Pengajuan premium baru')
            ->body("{$payment->user->name} mengirim bukti transfer paket {$payment->package->getLabel()}.")
            ->sendToDatabase($admins);
    }
}
