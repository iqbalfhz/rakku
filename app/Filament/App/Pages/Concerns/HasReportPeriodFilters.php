<?php

namespace App\Filament\App\Pages\Concerns;

use App\Services\ReportPeriod;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Filter periode (bulanan/tahunan) yang dipakai bersama oleh halaman laporan.
 */
trait HasReportPeriodFilters
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('period')
                            ->label('Periode')
                            ->options([
                                ReportPeriod::MONTHLY => 'Bulanan',
                                ReportPeriod::YEARLY => 'Tahunan',
                            ])
                            ->default(ReportPeriod::MONTHLY)
                            ->selectablePlaceholder(false),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn (): array => $this->yearOptions())
                            ->default(today()->year)
                            ->selectablePlaceholder(false),
                        Select::make('month')
                            ->label('Bulan')
                            ->options(fn (): array => $this->monthOptions())
                            ->default(today()->month)
                            ->selectablePlaceholder(false)
                            ->visible(fn (Get $get): bool => $get('period') !== ReportPeriod::YEARLY),
                    ]),
            ]);
    }

    /**
     * @return array<int, int>
     */
    private function yearOptions(): array
    {
        $years = range(today()->year, today()->year - 5);

        return array_combine($years, $years);
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => CarbonImmutable::create(2000, $month)->translatedFormat('F')])
            ->all();
    }
}
