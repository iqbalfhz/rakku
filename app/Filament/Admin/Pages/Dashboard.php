<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ExpiringPremiumTable;
use App\Filament\Admin\Widgets\NewUsersChart;
use App\Filament\Admin\Widgets\UserStatsOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    public function getWidgets(): array
    {
        return [
            UserStatsOverview::class,
            NewUsersChart::class,
            ExpiringPremiumTable::class,
        ];
    }
}
