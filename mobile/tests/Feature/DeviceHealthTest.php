<?php

use App\Models\DeviceReport;
use App\Services\DeviceDatabase;
use App\Services\DiagnosticsReporter;
use App\Services\SyncEngine;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->tokenStore = app(TokenStore::class);
    $this->tokenStore->rememberSession('token-rahasia', 'Budi');
    $this->tokenStore->rememberBook('01m3buku', 'Warung Kopi');
});

/**
 * Migrasi gagal sekali seperti di ponsel, lalu percobaan berikutnya berhasil.
 */
function makeMigrationFail(string $message = 'SQLSTATE: disk I/O error'): void
{
    Artisan::shouldReceive('call')
        ->with('migrate', ['--force' => true])
        ->once()
        ->andThrow(new RuntimeException($message));

    Artisan::shouldReceive('call')
        ->with('migrate', ['--force' => true])
        ->andReturn(0);
}

it('writes down a migration that failed instead of swallowing it', function () {
    makeMigrationFail();

    app(DeviceDatabase::class)->migrate();

    expect(app(DeviceDatabase::class)->hasFailed())->toBeTrue()
        ->and(app(DeviceDatabase::class)->failure()['message'])->toBe('SQLSTATE: disk I/O error');
});

it('queues the failure as a report for the developer', function () {
    makeMigrationFail();

    app(DeviceDatabase::class)->migrate();

    $report = DeviceReport::query()->sole();

    expect($report->kind)->toBe(DiagnosticsReporter::MIGRATION_FAILURE)
        ->and($report->message)->toContain('disk I/O error');
});

it('forgets the failure once the migration finally goes through', function () {
    makeMigrationFail();
    app(DeviceDatabase::class)->migrate();

    app(DeviceDatabase::class)->migrate();

    expect(app(DeviceDatabase::class)->hasFailed())->toBeFalse();
});

it('says so on screen instead of leaving the user with a broken app', function () {
    makeMigrationFail();
    app(DeviceDatabase::class)->migrate();

    $this->get(route('trouble'))
        ->assertSuccessful()
        ->assertSee('Penyimpanan')
        ->assertSee('disk I/O error');
});

it('puts a banner on every screen while the storage is broken', function () {
    makeMigrationFail();
    app(DeviceDatabase::class)->migrate();

    $this->get(route('trouble'))->assertSuccessful()->assertDontSee('Ketuk untuk melihat caranya');
    $this->get(route('home'))->assertSuccessful()->assertSee('Ketuk untuk melihat caranya');
});

it('leaves the banner off when nothing is wrong', function () {
    $this->get(route('home'))->assertSuccessful()->assertDontSee('Ketuk untuk melihat caranya');
});

it('lets the user try the repair again from that screen', function () {
    makeMigrationFail();
    app(DeviceDatabase::class)->migrate();

    Livewire::test('trouble')->call('retry')->assertSee('Berhasil diperbaiki');

    expect(app(DeviceDatabase::class)->hasFailed())->toBeFalse();
});

it('does not repeat the same complaint over and over', function () {
    $reporter = app(DiagnosticsReporter::class);

    $reporter->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    $reporter->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    $reporter->report(DiagnosticsReporter::CRASH, 'Yang lain meledak');

    expect(DeviceReport::query()->count())->toBe(2);
});

it('speaks up again once the quiet window has passed', function () {
    $reporter = app(DiagnosticsReporter::class);
    $reporter->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');

    $this->travel(DiagnosticsReporter::DUPLICATE_WINDOW_HOURS + 1)->hours();
    $reporter->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');

    expect(DeviceReport::query()->count())->toBe(2);
});

it('refuses to fill the phone with its own complaints', function () {
    $reporter = app(DiagnosticsReporter::class);

    foreach (range(1, DiagnosticsReporter::QUEUE_LIMIT + 5) as $number) {
        $reporter->report(DiagnosticsReporter::CRASH, "Meledak nomor {$number}");
    }

    expect(DeviceReport::query()->count())->toBe(DiagnosticsReporter::QUEUE_LIMIT);
});

it('sends what piled up and clears it', function () {
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => ($request['reports'][0]['message'] ?? null) === 'Sesuatu meledak');

    expect(DeviceReport::query()->count())->toBe(0);
});

it('keeps the pile when the server cannot be reached', function () {
    Http::fake(['*/api/v1/device-reports' => Http::response('', 500)]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    expect(DeviceReport::query()->count())->toBe(1);
});

it('stays quiet on a phone nobody has signed in on', function () {
    Http::fake();
    $this->tokenStore->forget();

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertNothingSent();
    expect(DeviceReport::query()->count())->toBe(1);
});

it('sends the reports along with the usual sync', function () {
    Http::fake([
        '*/api/v1/device-reports' => Http::response(['received' => 1]),
        '*/api/v1/books/*/sync*' => Http::response([
            'server_time' => '2026-10-01T03:00:00Z',
            'accounts' => [],
            'categories' => [],
            'transactions' => [],
        ]),
    ]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');

    app(SyncEngine::class)->sync();

    expect(DeviceReport::query()->count())->toBe(0);
});

it('says which version of the app was running when it broke', function () {
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);
    config(['nativephp.version' => '1.4.0']);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => ($request['reports'][0]['context']['app_version'] ?? null) === '1.4.0');
});

it('survives a runtime that does not set PHP_SELF, as the phone does not', function () {
    $original = $_SERVER['PHP_SELF'] ?? null;
    unset($_SERVER['PHP_SELF']);

    try {
        app(DeviceDatabase::class)->migrate();
    } finally {
        $_SERVER['PHP_SELF'] = $original;
    }

    expect(app(DeviceDatabase::class)->hasFailed())->toBeFalse();
});

it('does not re-run migrations on every screen once everything is applied', function () {
    Artisan::shouldReceive('call')->never();

    app(DeviceDatabase::class)->migrateIfNeeded();

    expect(app(DeviceDatabase::class)->hasFailed())->toBeFalse();
});

it('runs them again as soon as the app ships a new one', function () {
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);

    // Satu migrasi hilang dari catatan berarti aplikasi membawa yang belum diterapkan.
    DB::table('migrations')->orderByDesc('id')->limit(1)->delete();

    app(DeviceDatabase::class)->migrateIfNeeded();
});

it('runs them when the migrations table is not there at all', function () {
    Artisan::shouldReceive('call')->with('migrate', ['--force' => true])->once()->andReturn(0);

    Schema::drop('migrations');

    app(DeviceDatabase::class)->migrateIfNeeded();
});

it('ignores migrations recorded by packages when deciding if it is up to date', function () {
    Artisan::shouldReceive('call')->never();

    // NativePHP menyumbang migrasi dari dalam paketnya, jadi catatan di database
    // selalu lebih banyak daripada isi database/migrations.
    DB::table('migrations')->insert([
        'migration' => '9999_12_31_000000_create_jobs_table',
        'batch' => 1,
    ]);

    app(DeviceDatabase::class)->migrateIfNeeded();
});
