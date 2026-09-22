<?php

namespace App\Filament\App\Widgets;

use App\Enums\TransactionType;
use App\Filament\App\Widgets\Concerns\InteractsWithLedgerReport;
use App\Support\Rupiah;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Rincian laba-rugi per kategori untuk satu jenis transaksi (pendapatan atau beban).
 */
class CategoryBreakdownTable extends TableWidget
{
    use InteractsWithLedgerReport;
    use InteractsWithPageFilters;

    public string $transactionType = 'expense';

    public function table(Table $table): Table
    {
        $type = TransactionType::from($this->transactionType);

        return $table
            ->heading($type === TransactionType::Income ? 'Pendapatan per kategori' : 'Beban per kategori')
            ->records(fn (): array => $this->breakdownRows($type))
            ->paginated(false)
            ->emptyStateHeading('Belum ada transaksi pada periode ini')
            ->columns([
                TextColumn::make('category')
                    ->label('Kategori'),
                TextColumn::make('total')
                    ->label('Jumlah')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES)
                    ->color($type->getColor()),
                TextColumn::make('share')
                    ->label('Porsi')
                    ->suffix('%'),
            ]);
    }

    /**
     * @return array<int, array{category: string, total: float, share: float}>
     */
    private function breakdownRows(TransactionType $type): array
    {
        $period = $this->reportPeriod();
        $totals = $this->ledgerReport()->totalsByCategory($type, $period->from, $period->until);
        $grandTotal = $totals->sum('total');

        return $totals
            ->map(fn (array $row): array => [
                ...$row,
                'share' => $grandTotal > 0 ? round($row['total'] / $grandTotal * 100, 1) : 0.0,
            ])
            ->values()
            ->keyBy(fn (array $row, int $index): int => $index + 1)
            ->all();
    }
}
