<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Percentage;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class UserStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totalUsers = User::query()->count();
        $premiumUsers = User::query()->premium()->count();
        $expiringUsers = User::query()->premiumExpiringWithin(ExpiringPremiumTable::WINDOW_DAYS)->count();

        return [
            Stat::make('Total pengguna', Number::format($totalUsers))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->description('Semua akun terdaftar')
                ->color('primary'),
            Stat::make('Pengguna premium', Number::format($premiumUsers))
                ->descriptionIcon(Heroicon::OutlinedSparkles)
                ->description($totalUsers > 0
                    ? Number::format($premiumUsers / $totalUsers * 100, maxPrecision: 1).'% dari total pengguna'
                    : 'Belum ada pengguna')
                ->color('warning'),
            $this->newUsersStat(),
            Stat::make('Premium segera berakhir', Number::format($expiringUsers))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->description('Dalam '.ExpiringPremiumTable::WINDOW_DAYS.' hari ke depan')
                ->color($expiringUsers > 0 ? 'danger' : 'gray'),
        ];
    }

    private function newUsersStat(): Stat
    {
        $lastMonth = today()->subMonthNoOverflow();
        $thisMonthCount = User::query()->where('created_at', '>=', today()->startOfMonth())->count();
        $lastMonthCount = User::query()->whereBetween('created_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])->count();
        $changePercent = Percentage::change($thisMonthCount, $lastMonthCount);
        $isGrowing = $thisMonthCount >= $lastMonthCount;

        return Stat::make('Pengguna baru bulan ini', Number::format($thisMonthCount))
            ->description($changePercent === null
                ? "Bulan lalu {$lastMonthCount} pengguna"
                : sprintf('%s%s%% dari bulan lalu (%d)', $isGrowing ? '+' : '', $changePercent, $lastMonthCount))
            ->descriptionIcon($isGrowing ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->color($isGrowing ? 'success' : 'danger');
    }
}
