<?php

namespace App\Filament\App\Resources\Debts\RelationManagers;

use App\Enums\DebtStatus;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Debt;
use App\Support\Rupiah;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat cicilan';

    protected static ?string $modelLabel = 'cicilan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_id')
                    ->label('Akun')
                    ->helperText('Akun yang menerima/membayar cicilan. Transaksinya dicatat otomatis.')
                    ->relationship('account', 'name')
                    ->preload()
                    ->required(),
                MoneyInput::make('amount')
                    ->label('Nominal')
                    ->minValue(1)
                    ->maxValue(fn (): float => (float) $this->currentDebt()->remaining_amount)
                    ->default(fn (): float => (float) $this->currentDebt()->remaining_amount)
                    ->required(),
                DatePicker::make('payment_date')
                    ->label('Tanggal bayar')
                    ->default(today())
                    ->required(),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => 'Sisa: '.Rupiah::format((float) $this->currentDebt()->remaining_amount))
            ->defaultSort('payment_date', 'desc')
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y'),
                TextColumn::make('account.name')
                    ->label('Akun'),
                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money(Rupiah::CURRENCY, decimalPlaces: Rupiah::DECIMAL_PLACES),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(40),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Catat cicilan')
                    ->hidden(fn (): bool => $this->currentDebt()->status === DebtStatus::Paid),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('Transaksi yang tercatat dari cicilan ini ikut dihapus dan saldo akun dikembalikan.'),
            ]);
    }

    /**
     * Ambil ulang data utang agar sisa & status selalu mengikuti cicilan terbaru.
     */
    private function currentDebt(): Debt
    {
        /** @var Debt $debt */
        $debt = $this->getOwnerRecord();

        return $debt->refresh();
    }
}
