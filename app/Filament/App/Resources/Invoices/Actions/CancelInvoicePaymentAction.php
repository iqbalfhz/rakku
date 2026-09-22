<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Actions\CancelInvoicePayment;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class CancelInvoicePaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Batalkan pelunasan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (Invoice $record): bool => $record->isPaid())
            ->requiresConfirmation()
            ->modalDescription('Transaksi pemasukan dari pelunasan ini akan dihapus dan saldo akun dikembalikan.')
            ->action(function (Invoice $record, CancelInvoicePayment $cancelInvoicePayment): void {
                $cancelInvoicePayment->handle($record);

                $this->successNotificationTitle('Pelunasan dibatalkan');
                $this->success();
            });
    }
}
