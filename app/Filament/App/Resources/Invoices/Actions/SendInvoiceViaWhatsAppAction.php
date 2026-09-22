<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Rupiah;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Buka chat WhatsApp klien berisi ringkasan invoice dan link PDF; bisa dipakai berulang untuk kirim ulang.
 */
class SendInvoiceViaWhatsAppAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendViaWhatsApp';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Invoice $record): string => $record->status === InvoiceStatus::Draft ? 'Kirim via WhatsApp' : 'Kirim ulang via WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('success')
            ->visible(fn (Invoice $record): bool => ! $record->isPaid())
            ->modalHeading(fn (Invoice $record): string => "Kirim {$record->invoice_number} via WhatsApp")
            ->modalSubmitActionLabel('Buka WhatsApp')
            ->fillForm(fn (Invoice $record): array => [
                'phone' => $record->client->phone,
                'save_to_client' => true,
            ])
            ->schema([
                TextInput::make('phone')
                    ->label('Nomor WhatsApp klien')
                    ->tel()
                    ->regex('/^\+?[0-9\s\-]{9,20}$/')
                    ->validationMessages(['regex' => 'Nomor WhatsApp tidak valid.'])
                    ->helperText('Contoh: 081234567890 atau +6281234567890.')
                    ->required(),
                Toggle::make('save_to_client')
                    ->label('Simpan nomor ini ke data klien'),
            ])
            ->action(function (Invoice $record, array $data, Component $livewire): void {
                if ($data['save_to_client']) {
                    $record->client->update(['phone' => $data['phone']]);
                }

                $record->markAsSentIfDraft();

                $whatsAppUrl = WhatsApp::chatUrl($data['phone'], $this->message($record));

                $livewire->js('window.open('.Js::from($whatsAppUrl).', "_blank")');

                Notification::make()
                    ->success()
                    ->title('WhatsApp dibuka di tab baru')
                    ->body('Jika tidak terbuka otomatis, klik tombol di bawah.')
                    ->actions([
                        Action::make('openWhatsApp')
                            ->label('Buka WhatsApp')
                            ->url($whatsAppUrl, shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    private function message(Invoice $invoice): string
    {
        $invoice->loadMissing(['book', 'client', 'items']);

        return implode("\n", [
            "Halo {$invoice->client->name},",
            '',
            "Berikut invoice {$invoice->invoice_number} dari {$invoice->book->name}.",
            'Total: '.Rupiah::format($invoice->totalAmount()),
            'Jatuh tempo: '.$invoice->due_date->translatedFormat('d F Y'),
            '',
            'Unduh PDF: '.$invoice->sharedPdfUrl(),
            '',
            'Terima kasih.',
        ]);
    }
}
