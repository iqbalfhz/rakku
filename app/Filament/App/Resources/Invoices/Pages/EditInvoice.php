<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Filament\App\Resources\Invoices\Actions\CancelInvoicePaymentAction;
use App\Filament\App\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\App\Resources\Invoices\Actions\MarkInvoiceAsPaidAction;
use App\Filament\App\Resources\Invoices\Actions\MarkInvoiceAsSentAction;
use App\Filament\App\Resources\Invoices\Actions\SendInvoiceViaEmailAction;
use App\Filament\App\Resources\Invoices\Actions\SendInvoiceViaWhatsAppAction;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Invoice $record
 */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                SendInvoiceViaWhatsAppAction::make(),
                SendInvoiceViaEmailAction::make(),
                MarkInvoiceAsSentAction::make(),
            ])
                ->label('Kirim')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->button(),
            MarkInvoiceAsPaidAction::make()->after(fn () => $this->refreshPaidState()),
            CancelInvoicePaymentAction::make()->after(fn () => $this->refreshPaidState()),
            DownloadInvoicePdfAction::make(),
            DeleteAction::make()
                ->hidden(fn (Invoice $record): bool => $record->isPaid()),
        ];
    }

    /**
     * Invoice yang sudah lunas hanya bisa dilihat, tombol simpan disembunyikan.
     */
    protected function getFormActions(): array
    {
        return $this->record->isPaid() ? [] : parent::getFormActions();
    }

    /**
     * Muat ulang halaman agar form terkunci/terbuka sesuai status lunas terbaru.
     */
    private function refreshPaidState(): void
    {
        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
    }
}
