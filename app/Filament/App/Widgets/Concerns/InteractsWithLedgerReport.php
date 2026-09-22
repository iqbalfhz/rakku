<?php

namespace App\Filament\App\Widgets\Concerns;

use App\Models\Book;
use App\Services\LedgerReport;
use App\Services\ReportPeriod;
use Filament\Facades\Filament;
use Illuminate\Support\Number;

trait InteractsWithLedgerReport
{
    protected function ledgerReport(): LedgerReport
    {
        /** @var Book $book */
        $book = Filament::getTenant();

        return new LedgerReport($book);
    }

    /**
     * Periode dari filter halaman laporan (butuh trait InteractsWithPageFilters).
     */
    protected function reportPeriod(): ReportPeriod
    {
        return ReportPeriod::fromFilters($this->pageFilters);
    }

    protected function formatRupiah(float $amount): string
    {
        return Number::currency($amount, 'IDR', precision: 0);
    }
}
