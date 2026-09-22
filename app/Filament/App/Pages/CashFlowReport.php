<?php

namespace App\Filament\App\Pages;

use App\Filament\App\NavigationGroup;
use App\Filament\App\Pages\Concerns\HasReportPeriodFilters;
use App\Filament\App\Widgets\CashFlowChart;
use App\Filament\App\Widgets\CashFlowOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CashFlowReport extends BaseDashboard
{
    use HasReportPeriodFilters;

    protected static string $routePath = 'laporan/cash-flow';

    protected static ?string $title = 'Laporan Cash Flow';

    protected static ?string $navigationLabel = 'Cash Flow';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::PresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::ReportsAndBudgets;

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            CashFlowOverview::class,
            CashFlowChart::class,
        ];
    }
}
