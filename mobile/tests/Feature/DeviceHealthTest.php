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
use Native\Mobile\Testing\FakeBridge;

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

/**
 * Menirukan jawaban jembatan NativePHP dari sebuah ponsel Samsung.
 */
function pretendPhone(): void
{
    FakeBridge::current()
        ?->respondTo('Device.GetInfo', ['info' => json_encode([
            'manufacturer' => 'samsung',
            'model' => 'SM-A536E',
            'operatingSystem' => 'Android',
            'osVersion' => '14',
            'androidSDKVersion' => 34,
            'language' => 'id-ID',
            'isVirtual' => false,
            'webViewVersion' => '131.0.6778.81',
        ])])
        ->respondTo('Device.GetId', ['id' => 'a1b2c3d4e5f6a7b8']);
}

it('says which phone broke, not just that one did', function () {
    pretendPhone();
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(function ($request): bool {
        $context = $request['reports'][0]['context'];

        return $context['device'] === 'Samsung SM-A536E'
            && $context['os'] === 'Android 14'
            && $context['sdk'] === '34'
            && $context['language'] === 'id-ID'
            && $context['webview'] === '131.0.6778.81'
            && $context['is_virtual'] === false;
    });
});

/**
 * Pengganti alamat MAC, yang sejak Android 6 selalu terbaca 02:00:00:00:00:00
 * dan dilarang diminta oleh Play Store.
 */
it('carries a device id that survives a reinstall but not a factory reset', function () {
    pretendPhone();
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => $request['reports'][0]['context']['device_id'] === 'a1b2c3d4e5f6a7b8');
});

it('leaves out what the phone could not tell it', function () {
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(function ($request): bool {
        $context = $request['reports'][0]['context'];

        return ! array_key_exists('device', $context)
            && ! array_key_exists('device_id', $context)
            && $context['app_version'] !== null;
    });
});

/**
 * "Undefined array key PHP_SELF" memberi tahu apa yang pecah, bukan di mana.
 */
it('writes down where an exception happened, not only what it said', function () {
    $exception = new RuntimeException('Sesuatu meledak');

    app(DiagnosticsReporter::class)->reportThrowable(DiagnosticsReporter::CRASH, $exception);

    $report = DeviceReport::query()->sole();

    expect($report->message)->toBe('Sesuatu meledak')
        ->and($report->detail)->toContain('RuntimeException: Sesuatu meledak')
        ->and($report->detail)->toContain(basename(__FILE__))
        ->and($report->detail)->toContain('baris '.$exception->getLine());
});

it('sends the full trace along with the report', function () {
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->reportThrowable(DiagnosticsReporter::CRASH, new RuntimeException('Sesuatu meledak'));
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => str_contains((string) $request['reports'][0]['detail'], 'RuntimeException'));
});

it('cuts a trace too long to be worth carrying', function () {
    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Meledak', str_repeat('a', 9000));

    expect(mb_strlen(DeviceReport::query()->sole()->detail))->toBe(DiagnosticsReporter::DETAIL_LIMIT);
});

/**
 * Crash adalah sumber laporan yang paling sering, jadi justru di sinilah jejak
 * tumpukan paling dibutuhkan — bukan hanya pada migrasi yang gagal.
 */
it('reports an uncaught error with its trace, not just its sentence', function () {
    report(new RuntimeException('Layar ini meledak'));

    $report = DeviceReport::query()->where('kind', DiagnosticsReporter::CRASH)->sole();

    expect($report->message)->toBe('Layar ini meledak')
        ->and($report->detail)->toContain('RuntimeException: Layar ini meledak')
        ->and($report->detail)->toContain(basename(__FILE__));
});

it('falls back to the exception class when it carries no sentence', function () {
    app(DiagnosticsReporter::class)->reportThrowable(DiagnosticsReporter::CRASH, new RuntimeException);

    expect(DeviceReport::query()->sole()->message)->toBe(RuntimeException::class);
});

/**
 * Laporan menunggu sinyal, dan sementara menunggu, aplikasinya bisa keburu
 * diperbarui. Kalau keadaannya baru direkam saat kirim, laporan itu akan
 * menyalahkan versi yang tidak pernah mengalaminya.
 */
it('remembers the version the crash happened on, not the one installed later', function () {
    pretendPhone();
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    config(['nativephp.version' => '1.0.0']);
    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');

    config(['nativephp.version' => '1.1.0']);
    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => $request['reports'][0]['context']['app_version'] === '1.0.0');
});

it('still describes a report that was queued before contexts were kept', function () {
    pretendPhone();
    Http::fake(['*/api/v1/device-reports' => Http::response(['received' => 1])]);

    app(DiagnosticsReporter::class)->report(DiagnosticsReporter::CRASH, 'Sesuatu meledak');
    DeviceReport::query()->update(['context' => null]);

    app(DiagnosticsReporter::class)->flush();

    Http::assertSent(fn ($request): bool => $request['reports'][0]['context']['device'] === 'Samsung SM-A536E');
});
