<?php

namespace App\Filament\Admin\Resources\DeviceReports\Tables;

use App\Enums\DeviceReportKind;
use App\Models\DeviceReport;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeviceReportsTable
{
    /**
     * Urutan kolomnya mengikuti urutan pertanyaan yang muncul saat membuka halaman
     * ini: kapan, jenis apa, pesannya apa, di perangkat mana, dari mana, siapa.
     * Rincian yang hanya dibutuhkan setelah satu laporan dicurigai — jejak tumpukan,
     * level SDK, ID perangkat — ditaruh di balik tombol Lihat supaya tabelnya tetap
     * bisa dipindai sekilas.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Kabar kerusakan yang dikirim aplikasi ponsel pengguna.')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('kind')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('message')
                    ->label('Pesan')
                    ->wrap()
                    ->limit(120)
                    ->searchable(),
                TextColumn::make('device')
                    ->label('Perangkat')
                    ->state(fn (DeviceReport $record): string => self::fact($record, 'device', 'Tidak diketahui'))
                    ->description(fn (DeviceReport $record): string => self::deviceLine($record))
                    ->wrap(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->description(fn (DeviceReport $record): string => $record->user->email)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Jenis')
                    ->options(DeviceReportKind::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat')
                    ->modalHeading('Laporan kerusakan')
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema([
                        Section::make('Kejadian')->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                                TextEntry::make('kind')->label('Jenis')->badge(),
                                TextEntry::make('user.email')->label('Pengguna'),
                            ]),
                            TextEntry::make('message')->label('Pesan')->copyable(),
                        ]),
                        Section::make('Perangkat')->schema([
                            Grid::make(3)->schema([
                                TextEntry::make('deviceName')
                                    ->label('Merek & model')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'device')),
                                TextEntry::make('os')
                                    ->label('Sistem operasi')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'os', self::fact($record, 'platform'))),
                                TextEntry::make('sdk')
                                    ->label('Level SDK')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'sdk')),
                                TextEntry::make('appVersion')
                                    ->label('Versi aplikasi')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'app_version')),
                                TextEntry::make('webview')
                                    ->label('WebView')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'webview')),
                                TextEntry::make('language')
                                    ->label('Bahasa')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'language')),
                                TextEntry::make('phpVersion')
                                    ->label('PHP')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'php_version')),
                                // Kerusakan yang hanya muncul di emulator tidak layak dikejar
                                // sekeras kerusakan yang mengenai ponsel sungguhan.
                                TextEntry::make('isVirtual')
                                    ->label('Emulator')
                                    ->state(fn (DeviceReport $record): string => self::fact($record, 'is_virtual')),
                                TextEntry::make('ip_address')
                                    ->label('Alamat IP')
                                    ->placeholder('—')
                                    ->copyable(),
                            ]),
                            // Pengganti alamat MAC, yang sejak Android 6 tidak lagi bisa
                            // dibaca aplikasi mana pun dan dilarang diminta oleh Play Store.
                            // Pengenal ini khusus per aplikasi dan hilang saat reset pabrik.
                            TextEntry::make('deviceId')
                                ->label('ID perangkat')
                                ->state(fn (DeviceReport $record): string => self::fact($record, 'device_id'))
                                ->fontFamily(FontFamily::Mono)
                                ->copyable(),
                        ]),
                        Section::make('Jejak lengkap')
                            ->schema([
                                TextEntry::make('detail')
                                    ->hiddenLabel()
                                    ->state(fn (DeviceReport $record): array => explode("\n", (string) $record->detail))
                                    ->listWithLineBreaks()
                                    ->fontFamily(FontFamily::Mono)
                                    ->size(TextSize::ExtraSmall),
                            ])
                            ->collapsible()
                            ->visible(fn (DeviceReport $record): bool => filled($record->detail)),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    /**
     * Satu baris ringkas di bawah nama perangkat.
     *
     * Laporan lama hanya membawa `platform`, jadi `os` dibiarkan mundur ke sana
     * daripada menampilkan strip pada catatan yang sebenarnya masih berguna.
     */
    private static function deviceLine(DeviceReport $record): string
    {
        $context = $record->context ?? [];

        return collect([
            $context['os'] ?? $context['platform'] ?? null,
            $context['app_version'] ?? null,
        ])->filter()->implode(' · ') ?: '—';
    }

    /**
     * Satu keterangan dari konteks laporan, atau strip kalau ponsel tidak
     * mengirimkannya — versi lama aplikasi mengirim jauh lebih sedikit.
     */
    private static function fact(DeviceReport $record, string $key, string $fallback = '—'): string
    {
        $value = ($record->context ?? [])[$key] ?? null;

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Bukan';
        }

        return filled($value) ? (string) $value : $fallback;
    }
}
