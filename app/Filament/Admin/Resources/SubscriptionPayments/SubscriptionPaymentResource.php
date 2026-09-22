<?php

namespace App\Filament\Admin\Resources\SubscriptionPayments;

use App\Filament\Admin\Resources\SubscriptionPayments\Pages\ListSubscriptionPayments;
use App\Filament\Admin\Resources\SubscriptionPayments\Tables\SubscriptionPaymentsTable;
use App\Models\SubscriptionPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Banknotes;

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?string $pluralModelLabel = 'pembayaran';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return SubscriptionPaymentsTable::configure($table);
    }

    /**
     * Tampilkan jumlah pengajuan yang belum diverifikasi di menu admin.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendingCount = SubscriptionPayment::query()->pending()->count();

        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPayments::route('/'),
        ];
    }
}
