<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Rentang tanggal laporan hasil filter periode bulanan/tahunan.
 */
final readonly class ReportPeriod
{
    public const string MONTHLY = 'monthly';

    public const string YEARLY = 'yearly';

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $until,
        public bool $isYearly,
    ) {}

    /**
     * @param  array{period?: string|null, year?: int|string|null, month?: int|string|null}|null  $filters
     */
    public static function fromFilters(?array $filters): self
    {
        $year = (int) ($filters['year'] ?? today()->year);

        if (($filters['period'] ?? self::MONTHLY) === self::YEARLY) {
            $start = CarbonImmutable::create($year)->startOfYear();

            return new self($start, $start->endOfYear(), isYearly: true);
        }

        $start = CarbonImmutable::create($year, (int) ($filters['month'] ?? today()->month))->startOfMonth();

        return new self($start, $start->endOfMonth(), isYearly: false);
    }

    public function label(): string
    {
        return $this->isYearly
            ? "Tahun {$this->from->year}"
            : $this->from->translatedFormat('F Y');
    }
}
