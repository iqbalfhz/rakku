<?php

namespace App\Filament\App\Resources\RecurringTransactions\Pages;

use App\Filament\App\Resources\RecurringTransactions\RecurringTransactionResource;
use App\Models\RecurringTransaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRecurringTransactions extends ManageRecords
{
    protected static string $resource = RecurringTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(fn (RecurringTransaction $record) => $record->generateDueTransactions(today())),
        ];
    }
}
