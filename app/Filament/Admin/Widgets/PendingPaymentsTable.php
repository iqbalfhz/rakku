<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SubscriptionPayments\Tables\SubscriptionPaymentsTable;
use App\Models\SubscriptionPayment;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bukti transfer yang belum diverifikasi, diurutkan dari yang paling lama menunggu.
 */
class PendingPaymentsTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return SubscriptionPaymentsTable::configure($table)
            ->heading('Menunggu verifikasi')
            ->description('Pengajuan premium yang sudah mengirim bukti transfer.')
            ->query(fn (): Builder => SubscriptionPayment::query()->pending()->with('user'))
            ->defaultSort('created_at')
            ->filters([])
            ->emptyStateHeading('Tidak ada pengajuan yang menunggu');
    }
}
