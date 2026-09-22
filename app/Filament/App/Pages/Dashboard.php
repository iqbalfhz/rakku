<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BalanceOverview;
use App\Filament\App\Widgets\CashFlowTrendChart;
use App\Filament\App\Widgets\CategoryComparisonChart;
use App\Filament\App\Widgets\MonthOverMonthOverview;
use App\Filament\App\Widgets\TopExpenseCategoriesChart;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Ringkasan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    public function getWidgets(): array
    {
        return [
            BalanceOverview::class,
            CashFlowTrendChart::class,
            TopExpenseCategoriesChart::class,
            CategoryComparisonChart::class,
            MonthOverMonthOverview::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }
}
