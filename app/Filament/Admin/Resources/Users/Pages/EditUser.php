<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\Actions\ActivatePremiumAction;
use App\Filament\Admin\Resources\Users\Actions\DeleteUserAction;
use App\Filament\Admin\Resources\Users\Actions\DowngradeToFreeAction;
use App\Filament\Admin\Resources\Users\Actions\ToggleAdminAction;
use App\Filament\Admin\Resources\Users\Actions\VerifyEmailManuallyAction;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivatePremiumAction::make()->after(fn () => $this->refreshSubscription()),
            DowngradeToFreeAction::make()->after(fn () => $this->refreshSubscription()),
            VerifyEmailManuallyAction::make(),
            ToggleAdminAction::make(),
            DeleteUserAction::make()->successRedirectUrl(fn (): string => UserResource::getUrl('index')),
        ];
    }

    private function refreshSubscription(): void
    {
        $this->record->load('currentSubscription');
        $this->dispatch('refreshRelationManager');
    }
}
