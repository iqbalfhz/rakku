<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\Accounts\AccountResource;
use App\Filament\App\Resources\Budgets\BudgetResource;
use App\Filament\App\Resources\Transactions\TransactionResource;
use App\Models\Book;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Penunjuk jalan untuk buku yang masih kosong; hilang sendiri setelah semua langkah selesai.
 */
class GettingStarted extends Widget
{
    protected string $view = 'filament.app.widgets.getting-started';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return collect(self::stepsFor(Filament::getTenant()))->contains(fn (array $step): bool => ! $step['isDone']);
    }

    /**
     * @return list<array{title: string, body: string, url: string, isDone: bool}>
     */
    public function getSteps(): array
    {
        return self::stepsFor(Filament::getTenant());
    }

    public function getRemainingCount(): int
    {
        return count(array_filter($this->getSteps(), fn (array $step): bool => ! $step['isDone']));
    }

    /**
     * @return list<array{title: string, body: string, url: string, isDone: bool}>
     */
    private static function stepsFor(?Book $book): array
    {
        if ($book === null) {
            return [];
        }

        return [
            [
                'title' => 'Buat akun atau dompet',
                'body' => 'Tempat uang Anda berada: kas laci, rekening bank, atau e-wallet. Isi saldo awalnya sekali saja.',
                'url' => AccountResource::getUrl('index'),
                'isDone' => $book->accounts()->exists(),
            ],
            [
                'title' => 'Catat transaksi pertama',
                'body' => 'Satu pemasukan atau pengeluaran hari ini sudah cukup untuk mulai. Saldo akan menyesuaikan sendiri.',
                'url' => TransactionResource::getUrl('index'),
                'isDone' => $book->transactions()->exists(),
            ],
            [
                'title' => 'Tentukan budget bulanan',
                'body' => 'Pilih kategori yang paling boros, lalu beri batas. Langkah ini opsional, tapi paling terasa manfaatnya.',
                'url' => BudgetResource::getUrl('index'),
                'isDone' => $book->budgets()->exists(),
            ],
        ];
    }
}
