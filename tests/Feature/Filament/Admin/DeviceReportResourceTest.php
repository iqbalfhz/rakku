<?php

use App\Enums\DeviceReportKind;
use App\Filament\Admin\Resources\DeviceReports\Pages\ListDeviceReports;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    $this->admin = User::factory()->admin()->create();

    $this->actingAs($this->admin);
});

/**
 * Laporan dari sebuah ponsel Samsung, selengkap yang dikirim aplikasi terbaru.
 */
function reportFromAPhone(User $user, array $attributes = [])
{
    return $user->deviceReports()->create([
        'kind' => DeviceReportKind::Crash,
        'message' => 'Undefined array key "PHP_SELF"',
        'detail' => "ErrorException: Undefined array key \"PHP_SELF\"\n\nDi /app/vendor/symfony/console/Command/DumpCompletionCommand.php baris 34",
        'context' => [
            'platform' => 'Android',
            'device' => 'Samsung SM-A536E',
            'os' => 'Android 14',
            'sdk' => '34',
            'device_id' => 'a1b2c3d4e5f6a7b8',
            'is_virtual' => false,
            'webview' => '131.0.6778.81',
            'language' => 'id-ID',
            'app_version' => '1.0.0',
            'php_version' => '8.4.25',
        ],
        'ip_address' => '203.0.113.9',
        'occurred_at' => '2026-09-24 03:00:00',
        ...$attributes,
    ]);
}

it('names the phone and the address a report came from', function () {
    $report = reportFromAPhone($this->admin);

    Livewire::test(ListDeviceReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertSee('Samsung SM-A536E')
        ->assertSee('Android 14')
        ->assertSee('203.0.113.9');
});

it('keeps the stack trace behind the view button instead of in the row', function () {
    $report = reportFromAPhone($this->admin);

    Livewire::test(ListDeviceReports::class)
        ->assertDontSee('DumpCompletionCommand')
        ->mountAction(TestAction::make('view')->table($report))
        ->assertMountedActionModalSee('DumpCompletionCommand.php');
});

it('shows the rest of the device details only in the view', function () {
    $report = reportFromAPhone($this->admin);

    Livewire::test(ListDeviceReports::class)
        ->mountAction(TestAction::make('view')->table($report))
        ->assertMountedActionModalSee('a1b2c3d4e5f6a7b8')
        ->assertMountedActionModalSee('131.0.6778.81')
        ->assertMountedActionModalSee('id-ID');
});

/**
 * Ponsel yang belum diperbarui hanya mengirim platform dan versi aplikasi.
 * Laporannya tetap harus terbaca, bukan berubah jadi deretan strip.
 */
it('still reads a report from a phone running an older app', function () {
    $report = reportFromAPhone($this->admin, [
        'detail' => null,
        'ip_address' => null,
        'context' => ['platform' => 'Android', 'app_version' => '0.9.0', 'php_version' => '8.4.25'],
    ]);

    Livewire::test(ListDeviceReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertSee('Tidak diketahui')
        ->assertSee('Android · 0.9.0');
});
