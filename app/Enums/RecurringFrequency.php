<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum RecurringFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    /**
     * Hitung tanggal jalan berikutnya, tetap mengacu ke tanggal di $anchor
     * agar jadwal tanggal 31 tidak bergeser permanen setelah melewati Februari.
     */
    public function nextDate(CarbonInterface $current, CarbonInterface $anchor): CarbonImmutable
    {
        $current = $current->toImmutable();

        return match ($this) {
            self::Daily => $current->addDay(),
            self::Weekly => $current->addWeek(),
            self::Monthly => $this->clampToAnchorDay($current->addMonthNoOverflow(), $anchor),
            self::Yearly => $this->clampToAnchorDay($current->addYearNoOverflow(), $anchor),
        };
    }

    private function clampToAnchorDay(CarbonImmutable $date, CarbonInterface $anchor): CarbonImmutable
    {
        return $date->setDay(min($anchor->day, $date->daysInMonth));
    }
}
