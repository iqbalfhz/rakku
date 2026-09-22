<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Users\Actions\ActivatePremiumAction;
use App\Models\Subscription;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar pengguna yang premiumnya akan habis, diurutkan dari yang paling dekat.
 */
class ExpiringPremiumTable extends TableWidget
{
    public const int WINDOW_DAYS = 14;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Premium segera berakhir')
            ->description('Premium yang habis dalam '.self::WINDOW_DAYS.' hari ke depan.')
            ->query(fn (): Builder => User::query()
                ->premiumExpiringWithin(self::WINDOW_DAYS)
                ->with('currentSubscription')
                ->orderBy(Subscription::query()
                    ->select('expires_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest('id')
                    ->limit(1)))
            ->emptyStateHeading('Tidak ada premium yang akan berakhir')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('currentSubscription.expires_at')
                    ->label('Berakhir')
                    ->dateTime('d M Y H:i')
                    ->description(fn (User $record): string => self::remainingDaysLabel($record)),
            ])
            ->recordActions([
                ActivatePremiumAction::make()
                    ->label('Perpanjang'),
            ]);
    }

    private static function remainingDaysLabel(User $record): string
    {
        $remainingDays = (int) today()->diffInDays($record->currentSubscription->expires_at->startOfDay());

        return $remainingDays === 0 ? 'Berakhir hari ini' : "Sisa {$remainingDays} hari";
    }
}
