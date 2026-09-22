<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Actions\GenerateInvoicePdf;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadInvoicePdfAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'downloadPdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Unduh PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(fn (Invoice $record, GenerateInvoicePdf $generateInvoicePdf): StreamedResponse => response()->streamDownload(
                function () use ($record, $generateInvoicePdf): void {
                    echo $generateInvoicePdf->handle($record);
                },
                "{$record->invoice_number}.pdf",
                ['Content-Type' => 'application/pdf'],
            ));
    }
}
