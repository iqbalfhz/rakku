<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Actions\ExtendPremium;
use App\Enums\SubscriptionPackage;
use App\Enums\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ActivatePremiumAction extends Action
{
    public const string CUSTOM_DURATION = 'custom';

    public const string UNLIMITED_DURATION = 'unlimited';

    public static function getDefaultName(): ?string
    {
        return 'activatePremium';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Aktifkan premium')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('warning')
            ->modalDescription('Aktivasi manual, misalnya setelah pembayaran diterima di luar aplikasi. Untuk pengguna yang masih premium, masa aktifnya ditambahkan dari tanggal kedaluwarsa.')
            ->schema([
                ToggleButtons::make('duration')
                    ->label('Masa aktif')
                    ->options(self::durationOptions())
                    ->default(SubscriptionPackage::Monthly->value)
                    ->inline()
                    ->live()
                    ->required(),
                DatePicker::make('expires_at')
                    ->label('Berlaku sampai')
                    ->visible(fn (Get $get): bool => $get('duration') === self::CUSTOM_DURATION)
                    ->required(fn (Get $get): bool => $get('duration') === self::CUSTOM_DURATION)
                    ->after('today'),
            ])
            ->action(function (User $record, array $data): void {
                $expiresAt = self::expiryFor($record, $data);

                $this->successNotificationTitle($expiresAt === null
                    ? "{$record->name} sekarang premium tanpa batas waktu"
                    : "{$record->name} premium sampai {$expiresAt->translatedFormat('j F Y')}");
                $this->success();
            });
    }

    /**
     * Tombol durasi mengikuti paket yang dijual, ditambah dua pilihan khusus admin.
     *
     * @return array<string, string>
     */
    private static function durationOptions(): array
    {
        $packages = collect(SubscriptionPackage::cases())
            ->mapWithKeys(fn (SubscriptionPackage $package): array => [
                $package->value => "{$package->months()} bulan",
            ])
            ->all();

        return $packages + [
            self::CUSTOM_DURATION => 'Tanggal khusus',
            self::UNLIMITED_DURATION => 'Tanpa batas',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function expiryFor(User $user, array $data): ?CarbonImmutable
    {
        if ($data['duration'] === self::UNLIMITED_DURATION) {
            $user->subscribeTo(SubscriptionPlan::Premium);

            return null;
        }

        if ($data['duration'] === self::CUSTOM_DURATION) {
            $expiresAt = CarbonImmutable::parse($data['expires_at'])->endOfDay();

            $user->subscribeTo(SubscriptionPlan::Premium, $expiresAt);

            return $expiresAt;
        }

        return app(ExtendPremium::class)->handle($user, SubscriptionPackage::from($data['duration']));
    }
}
