<?php

namespace App\Filament\App\Resources\Budgets\Pages;

use App\Filament\App\Resources\Budgets\BudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBudgets extends ManageRecords
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
