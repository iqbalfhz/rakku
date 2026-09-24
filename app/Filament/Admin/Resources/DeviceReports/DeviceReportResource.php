<?php

namespace App\Filament\Admin\Resources\DeviceReports;

use App\Filament\Admin\Resources\DeviceReports\Pages\ListDeviceReports;
use App\Filament\Admin\Resources\DeviceReports\Tables\DeviceReportsTable;
use App\Models\DeviceReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeviceReportResource extends Resource
{
    /**
     * Laporan yang masih dianggap "baru" dan pantas ditunjukkan di menu.
     */
    private const int RECENT_DAYS = 7;

    protected static ?string $model = DeviceReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ExclamationTriangle;

    protected static ?string $modelLabel = 'laporan perangkat';

    protected static ?string $pluralModelLabel = 'laporan perangkat';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return DeviceReportsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $recentCount = DeviceReport::query()->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))->count();

        return $recentCount > 0 ? (string) $recentCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeviceReports::route('/'),
        ];
    }
}
