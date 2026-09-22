<?php

namespace App\Filament\App\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\Invoices\Actions\CancelInvoicePaymentAction;
use App\Filament\App\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\App\Resources\Invoices\Actions\MarkInvoiceAsPaidAction;
use App\Filament\App\Resources\Invoices\Actions\MarkInvoiceAsSentAction;
use App\Models\Invoice;
use App\Support\Rupiah;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTotalAmount())
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable(),
                TextColumn::make('issue_date')
                    ->label('Terbit')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh tempo')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Invoice $record): bool => $record->isPaid()),
                ActionGroup::make([
                    MarkInvoiceAsSentAction::make(),
                    MarkInvoiceAsPaidAction::make(),
                    CancelInvoicePaymentAction::make(),
                    DownloadInvoicePdfAction::make(),
                    DeleteAction::make()
                        ->hidden(fn (Invoice $record): bool => $record->isPaid()),
                ]),
            ]);
    }
}
