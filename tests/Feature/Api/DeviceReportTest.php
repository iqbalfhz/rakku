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
