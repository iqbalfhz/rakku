<?php

namespace App\Filament\App\Pages;

use App\Enums\TransactionType;
use App\Filament\App\Concerns\RequiresPremium;
use App\Filament\App\NavigationGroup;
use App\Filament\App\Pages\Concerns\HasReportPeriodFilters;
use App\Filament\App\Widgets\CategoryBreakdownTable;
use App\Filament\App\Widgets\ProfitLossOverview;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProfitLossReport extends BaseDashboard
{
    use HasReportPeriodFilters;
    use RequiresPremium;

    protected static string $routePath = 'laporan/laba-rugi';

    protected static ?string $title = 'Laporan Laba-Rugi';

    protected static ?string $navigationLabel = 'Laba-Rugi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::DocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::ReportsAndBudgets;

    protected static ?int $navigationSort = 2;

    public function getWidgets(): array
    {
        return [
            ProfitLossOverview::class,
            CategoryBreakdownTable::make(['transactionType' => TransactionType::Income->value]),
            CategoryBreakdownTable::make(['transactionType' => TransactionType::Expense->value]),
        ];
    }

    public function getColumns(): int|array
    {
        return ['md' => 2];
    }
}
