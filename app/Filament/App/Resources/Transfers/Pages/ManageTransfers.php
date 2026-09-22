<?php

namespace App\Filament\App\Resources\Transfers\Pages;

use App\Filament\App\Resources\Transfers\TransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTransfers extends ManageRecords
{
    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
