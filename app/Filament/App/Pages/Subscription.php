<?php

namespace App\Filament\App\Pages;

use App\Actions\SubmitSubscriptionPayment;
use App\Enums\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Rupiah;
use App\Support\SubscriptionConfig;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

    /**
     * Status langganan dan tujuan transfer, disusun dengan komponen Filament agar rapi di semua lebar layar.
     */
    public function overview(Schema $schema): Schema
    {
        $bank = SubscriptionConfig::bank();

        return $schema->components([
            Section::make('Status langganan')
                ->schema([
                    TextEntry::make('plan')
                        ->hiddenLabel()
                        ->state($this->user()->isPremium() ? 'Premium' : 'Free')
                        ->badge()
                        ->color($this->user()->isPremium() ? 'warning' : 'gray'),
                    TextEntry::make('expiry')
                        ->label('Masa aktif')
                        ->state(fn (): string => match (true) {
                            ! $this->user()->isPremium() => 'Utang-piutang, invoice, laporan laba-rugi, transaksi berulang, dan buku tambahan terbuka setelah premium aktif.',
                            $this->premiumExpiresAt() === null => 'Berlaku tanpa batas waktu.',
                            default => "Berlaku sampai {$this->premiumExpiresAt()}.",
                        }),
                ]),
            Section::make('Cara berlangganan')
                ->description('Transfer sesuai paket yang Anda pilih di bawah, lalu unggah bukti transfernya. Admin memverifikasi secara manual.')
                ->columns(3)
                ->schema([
                    TextEntry::make('bank_name')
                        ->label('Bank')
                        ->state($bank['name']),
                    TextEntry::make('bank_account_number')
                        ->label('Nomor rekening')
                        ->state($bank['account_number'])
                        ->copyable()
                        ->copyMessage('Nomor rekening disalin'),
                    TextEntry::make('bank_account_holder')
                        ->label('Atas nama')
                        ->state($bank['account_holder']),
                ]),
        ]);
    }

    /**
     * Riwayat pengajuan sebelumnya, termasuk alasan kalau pernah ditolak.
     */
    public function history(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Riwayat pengajuan')
                ->collapsible()
                ->collapsed()
                ->visible(fn (): bool => $this->paymentHistory()->isNotEmpty())
                ->schema($this->paymentHistory()
                    ->map(fn (SubscriptionPayment $payment): TextEntry => TextEntry::make("payment-{$payment->id}")
                        ->label($payment->created_at->translatedFormat('j F Y'))
                        ->state($this->historyLine($payment))
                        ->badge()
                        ->color($payment->status->getColor()))
                    ->all()),
        ]);
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

        app(SubmitSubscriptionPayment::class)->handle($this->user(), $package, $data['proof_path'], $data['note'] ?? null);

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

    private function historyLine(SubscriptionPayment $payment): string
    {
        $summary = sprintf(
            '%d bulan · %s · %s',
            $payment->package->months(),
            Rupiah::format((float) $payment->amount),
            $payment->status->getLabel(),
        );

        return $payment->rejection_reason === null ? $summary : "{$summary} — {$payment->rejection_reason}";
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

    /**
     * Tautan ke halaman ini untuk dipakai di notifikasi, lewat buku pertama milik pengguna.
     */
    public static function urlFor(User $user): string
    {
        $book = $user->books()->oldest('id')->first();

        return $book === null ? url('/app') : static::getUrl(panel: 'app', tenant: $book);
    }
}
