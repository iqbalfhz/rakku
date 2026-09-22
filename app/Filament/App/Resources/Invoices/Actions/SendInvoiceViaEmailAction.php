<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Actions\SendInvoiceByEmail;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Kirim invoice beserta PDF ke email klien; bisa dipakai berulang untuk kirim ulang.
 */
class SendInvoiceViaEmailAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendViaEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Invoice $record): string => $record->status === InvoiceStatus::Draft ? 'Kirim via email' : 'Kirim ulang via email')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('info')
            ->visible(fn (Invoice $record): bool => ! $record->isPaid())
            ->modalHeading(fn (Invoice $record): string => "Kirim {$record->invoice_number} via email")
            ->modalSubmitActionLabel('Kirim')
            ->fillForm(fn (Invoice $record): array => [
                'email' => $record->client->email,
                'save_to_client' => true,
            ])
            ->schema([
                TextInput::make('email')
                    ->label('Email klien')
                    ->email()
                    ->required(),
                Toggle::make('save_to_client')
                    ->label('Simpan email ini ke data klien'),
            ])
            ->action(function (Invoice $record, array $data, SendInvoiceByEmail $sendInvoiceByEmail): void {
                if ($data['save_to_client']) {
                    $record->client->update(['email' => $data['email']]);
                }

                try {
                    $sendInvoiceByEmail->handle($record, $data['email']);
                } catch (TransportExceptionInterface $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('Email gagal dikirim')
                        ->body('Periksa alamat email atau pengaturan server email, lalu coba lagi.')
                        ->send();

                    $this->halt();
                }

                $this->successNotificationTitle("Invoice {$record->invoice_number} terkirim ke {$data['email']}");
                $this->success();
            });
    }
}
