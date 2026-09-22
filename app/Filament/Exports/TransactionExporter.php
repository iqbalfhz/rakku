<?php

namespace App\Filament\Exports;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_date')
                ->label('Tanggal')
                ->formatStateUsing(fn (CarbonInterface $state): string => $state->toDateString()),
            ExportColumn::make('type')
                ->label('Jenis')
                ->formatStateUsing(fn (TransactionType $state): string => $state->getLabel()),
            ExportColumn::make('account.name')
                ->label('Akun'),
            ExportColumn::make('category.name')
                ->label('Kategori'),
            ExportColumn::make('amount')
                ->label('Nominal'),
            ExportColumn::make('description')
                ->label('Keterangan'),
            ExportColumn::make('created_at')
                ->label('Dicatat pada')
                ->enabledByDefault(false),
        ];
    }

    /**
     * Header tebal berlatar hijau untuk export Excel (premium).
     */
    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(5, 150, 105));
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export transaksi selesai';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = number_format($export->successful_rows, 0, ',', '.').' transaksi berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount, 0, ',', '.').' transaksi gagal diekspor.';
        }

        return $body;
    }
}
