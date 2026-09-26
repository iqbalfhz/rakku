<?php

use App\Enums\DeviceReportKind;
use App\Models\DeviceReport;
use App\Models\User;
use App\Notifications\DeviceReportReceived;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create();

    Sanctum::actingAs($this->user);
});

it('keeps a crash the phone reported', function () {
    $this->postJson('/api/v1/device-reports', [
        'reports' => [[
            'kind' => 'crash',
            'message' => 'SQLSTATE[HY000]: no such column: debts.public_id',
            'context' => ['platform' => 'Android 14', 'app_version' => '1.2.0'],
            'occurred_at' => '2026-09-24T03:00:00Z',
        ]],
    ])->assertSuccessful()->assertJson(['received' => 1]);

    $report = DeviceReport::query()->sole();

    expect($report->user_id)->toBe($this->user->id)
        ->and($report->kind)->toBe(DeviceReportKind::Crash)
        ->and($report->context['platform'])->toBe('Android 14');
});

it('wakes the admins for a migration that failed on someone phone', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->postJson('/api/v1/device-reports', [
        'reports' => [[
            'kind' => 'migration_failure',
            'message' => 'Migrasi 2026_09_24 gagal: table already exists',
            'occurred_at' => '2026-09-24T03:00:00Z',
        ]],
    ])->assertSuccessful();

    Notification::assertSentTo($admin, DeviceReportReceived::class);
});

it('leaves the admins alone for an ordinary error', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->postJson('/api/v1/device-reports', [
        'reports' => [[
            'kind' => 'crash',
            'message' => 'Sesuatu meledak',
            'occurred_at' => '2026-09-24T03:00:00Z',
        ]],
    ])->assertSuccessful();

    Notification::assertNothingSentTo($admin);
});

it('takes a whole batch that piled up while the phone was offline', function () {
    $this->postJson('/api/v1/device-reports', [
        'reports' => [
            ['kind' => 'crash', 'message' => 'Pertama', 'occurred_at' => '2026-09-24T03:00:00Z'],
            ['kind' => 'crash', 'message' => 'Kedua', 'occurred_at' => '2026-09-24T04:00:00Z'],
        ],
    ])->assertSuccessful()->assertJson(['received' => 2]);

    expect(DeviceReport::query()->count())->toBe(2);
});

it('refuses a flood from one request', function () {
    $reports = array_fill(0, 21, ['kind' => 'crash', 'message' => 'Berulang', 'occurred_at' => '2026-09-24T03:00:00Z']);

    $this->postJson('/api/v1/device-reports', ['reports' => $reports])
        ->assertJsonValidationErrorFor('reports');

    expect(DeviceReport::query()->count())->toBe(0);
});

it('turns down a kind it does not know', function () {
    $this->postJson('/api/v1/device-reports', [
        'reports' => [['kind' => 'gosip', 'message' => 'Entah', 'occurred_at' => '2026-09-24T03:00:00Z']],
    ])->assertJsonValidationErrorFor('reports.0.kind');
});

it('turns away a phone without a token', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/device-reports', ['reports' => []])->assertUnauthorized();
});

it('keeps the file and line the phone sent along with the message', function () {
    $this->postJson('/api/v1/device-reports', [
        'reports' => [[
            'kind' => 'crash',
            'message' => 'Undefined array key "PHP_SELF"',
            'detail' => "ErrorException: Undefined array key \"PHP_SELF\"\n\nDi /app/vendor/symfony/console/Command/DumpCompletionCommand.php baris 34",
            'occurred_at' => '2026-09-24T03:00:00Z',
        ]],
    ])->assertSuccessful();

    expect(DeviceReport::query()->sole()->detail)->toContain('DumpCompletionCommand.php baris 34');
});

it('records the address the report arrived from', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson('/api/v1/device-reports', [
            'reports' => [[
                'kind' => 'crash',
                'message' => 'Sesuatu meledak',
                'occurred_at' => '2026-09-24T03:00:00Z',
            ]],
        ])->assertSuccessful();

    expect(DeviceReport::query()->sole()->ip_address)->toBe('198.51.100.7');
});

/**
 * Di produksi permintaan sampai lewat Cloudflare Tunnel, jadi REMOTE_ADDR adalah
 * alamat tunnelnya — mencatat itu berarti setiap laporan mengaku datang dari
 * tempat yang sama.
 */
it('records the phone address, not the proxy standing in front of it', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson('/api/v1/device-reports', [
            'reports' => [[
                'kind' => 'crash',
                'message' => 'Sesuatu meledak',
                'occurred_at' => '2026-09-24T03:00:00Z',
            ]],
        ], ['X-Forwarded-For' => '203.0.113.9'])->assertSuccessful();

    expect(DeviceReport::query()->sole()->ip_address)->toBe('203.0.113.9');
});

it('ignores an address the phone claims for itself', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson('/api/v1/device-reports', [
            'reports' => [[
                'kind' => 'crash',
                'message' => 'Sesuatu meledak',
                'ip_address' => '203.0.113.200',
                'occurred_at' => '2026-09-24T03:00:00Z',
            ]],
        ])->assertSuccessful();

    expect(DeviceReport::query()->sole()->ip_address)->toBe('198.51.100.7');
});

it('turns away a stack trace too long to be worth keeping', function () {
    $this->postJson('/api/v1/device-reports', [
        'reports' => [[
            'kind' => 'crash',
            'message' => 'Sesuatu meledak',
            'detail' => str_repeat('a', 8001),
            'occurred_at' => '2026-09-24T03:00:00Z',
        ]],
    ])->assertInvalid('reports.0.detail');
});

it('names the phone in the email so the pattern shows from the inbox', function () {
    $admin = User::factory()->admin()->create();

    $report = $admin->deviceReports()->create([
        'kind' => DeviceReportKind::MigrationFailure,
        'message' => 'Migrasi gagal',
        'context' => ['device' => 'Samsung SM-A536E', 'os' => 'Android 14', 'app_version' => '1.0.0'],
        'ip_address' => '203.0.113.9',
        'occurred_at' => '2026-09-24 03:00:00',
    ]);

    $body = (string) (new DeviceReportReceived($report))->toMail($admin)->render();

    expect($body)->toContain('Samsung SM-A536E')
        ->and($body)->toContain('Android 14')
        ->and($body)->toContain('aplikasi 1.0.0')
        ->and($body)->toContain('203.0.113.9');
});

it('leaves the device line out when an older app told us nothing', function () {
    $admin = User::factory()->admin()->create();

    $report = $admin->deviceReports()->create([
        'kind' => DeviceReportKind::MigrationFailure,
        'message' => 'Migrasi gagal',
        'context' => null,
        'occurred_at' => '2026-09-24 03:00:00',
    ]);

    expect((string) (new DeviceReportReceived($report))->toMail($admin)->render())
        ->toContain('Migrasi gagal');
});
