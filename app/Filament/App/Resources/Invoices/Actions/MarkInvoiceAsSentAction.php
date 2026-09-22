<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class MarkInvoiceAsSentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsSent';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tandai terkirim')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('info')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft)
            ->requiresConfirmation()
            ->action(function (Invoice $record): void {
                $record->markAsSent();

                $this->successNotificationTitle("Invoice {$record->invoice_number} ditandai {$record->status->getLabel()}");
                $this->success();
            });
    }
}
