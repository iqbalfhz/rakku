<?php

use App\Filament\Exports\TransactionExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Storage;

/**
 * Tiru satu export yang sudah selesai: catatan di database plus filenya di disk.
 */
function completedExport(User $user, string $createdAt): Export
{
    $export = Export::query()->create([
        'completed_at' => $createdAt,
        'file_disk' => 'local',
        'file_name' => 'ekspor-transactions',
        'exporter' => TransactionExporter::class,
        'total_rows' => 1,
        'processed_rows' => 1,
        'successful_rows' => 1,
        'user_id' => $user->id,
        'created_at' => $createdAt,
    ]);

    Storage::disk('local')->put($export->getFileDirectory().'/ekspor-transactions.xlsx', 'isi export');

    return $export;
}

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('deletes exports older than the retention window together with their files', function () {
    $oldExport = completedExport($this->user, now()->subDays(91)->toDateTimeString());

    $this->artisan('app:prune-exports')
        ->expectsOutputToContain('1 export lebih tua dari 90 hari dihapus')
        ->assertSuccessful();

    expect(Export::query()->count())->toBe(0)
        ->and(Storage::disk('local')->directoryExists($oldExport->getFileDirectory()))->toBeFalse();
});

it('keeps exports that are still within the retention window', function () {
    $recentExport = completedExport($this->user, now()->subDays(89)->toDateTimeString());

    $this->artisan('app:prune-exports')
        ->expectsOutputToContain('0 export')
        ->assertSuccessful();

    expect(Export::query()->count())->toBe(1)
        ->and(Storage::disk('local')->exists($recentExport->getFileDirectory().'/ekspor-transactions.xlsx'))->toBeTrue();
});

it('accepts a shorter retention window from the command line', function () {
    completedExport($this->user, now()->subDays(10)->toDateTimeString());

    $this->artisan('app:prune-exports', ['--days' => 7])
        ->expectsOutputToContain('1 export lebih tua dari 7 hari dihapus')
        ->assertSuccessful();

    expect(Export::query()->count())->toBe(0);
});
