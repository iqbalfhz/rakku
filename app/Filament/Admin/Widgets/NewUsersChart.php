<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class NewUsersChart extends ChartWidget
{
    private const int MONTHS = 12;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Pengguna baru per bulan';

    protected ?string $description = '12 bulan terakhir';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $start = today()->subMonthsNoOverflow(self::MONTHS - 1)->startOfMonth();
        $months = collect(CarbonPeriod::create($start, '1 month', today()->startOfMonth()));

        $signUpsPerMonth = User::query()
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn (CarbonInterface $createdAt): string => $createdAt->format('Y-m'));

        return [
            'datasets' => [
                [
                    'label' => 'Pengguna baru',
                    'data' => $months->map(fn (CarbonInterface $month): int => $signUpsPerMonth[$month->format('Y-m')] ?? 0)->all(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $months->map(fn (CarbonInterface $month): string => $month->translatedFormat('M y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
