<?php

namespace App\Filament\App\Resources\Invoices\Actions;

use App\Actions\MarkInvoiceAsPaid;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class MarkInvoiceAsPaidAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'markAsPaid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Tandai lunas')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status->isPayable())
            ->modalDescription('Pemasukan sebesar total invoice akan dicatat otomatis ke akun yang dipilih.')
            ->schema([
                Select::make('account_id')
                    ->label('Diterima di akun')
                    ->options(fn (): array => $this->currentBook()->accounts()->orderBy('name')->pluck('name', 'id')->all())
                    ->required(),
                Select::make('category_id')
                    ->label('Kategori pemasukan')
                    ->options(fn (): array => $this->currentBook()->categories()->ofType(TransactionType::Income)->orderBy('name')->pluck('name', 'id')->all()),
                DatePicker::make('paid_at')
                    ->label('Tanggal pelunasan')
                    ->default(today())
                    ->required(),
            ])
            ->action(function (Invoice $record, array $data, MarkInvoiceAsPaid $markInvoiceAsPaid): void {
                $markInvoiceAsPaid->handle(
                    $record,
                    Account::query()->findOrFail($data['account_id']),
                    CarbonImmutable::parse($data['paid_at']),
                    $data['category_id'] ?? null,
                );

                $this->successNotificationTitle("Invoice {$record->invoice_number} lunas");
                $this->success();
            });
    }

    private function currentBook(): Book
    {
        /** @var Book */
        return Filament::getTenant();
    }
}
