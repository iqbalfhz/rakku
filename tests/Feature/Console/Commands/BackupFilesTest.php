<?php

use App\Console\Commands\BackupFiles;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->source = storage_path('framework/testing/uploads');
    $this->destination = storage_path('framework/testing/backups');

    File::deleteDirectory($this->source);
    File::deleteDirectory($this->destination);
    File::ensureDirectoryExists($this->source.'/receipts');

    config([
        'backup.source' => $this->source,
        'backup.destination' => $this->destination,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->source);
    File::deleteDirectory($this->destination);
});

it('archives every uploaded file, keeping its folder structure', function () {
    File::put($this->source.'/receipts/struk.jpg', 'isi foto struk');

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('1 file')
        ->assertSuccessful();

    $zip = new ZipArchive;
    $zip->open(File::glob($this->destination.'/'.BackupFiles::ARCHIVE_PREFIX.'*.zip')[0]);

    expect($zip->numFiles)->toBe(1)
        ->and($zip->getNameIndex(0))->toBe('receipts/struk.jpg')
        ->and($zip->getFromName('receipts/struk.jpg'))->toBe('isi foto struk');

    $zip->close();
});

it('leaves regenerable files out of the archive', function (string $directory) {
    File::ensureDirectoryExists($this->source.'/'.$directory);
    File::put($this->source.'/'.$directory.'/bisa-dibuat-ulang.tmp', 'isi apa saja');

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('Belum ada file unggahan')
        ->assertSuccessful();

    expect(File::glob($this->destination.'/*.zip'))->toBeEmpty();
})->with([
    'unggahan yang batal' => 'livewire-tmp',
    'hasil export' => 'filament_exports',
]);

it('skips the archive when nothing has been uploaded yet', function () {
    $this->artisan('app:backup-files')
        ->expectsOutputToContain('Belum ada file unggahan')
        ->assertSuccessful();

    expect(File::glob($this->destination.'/*.zip'))->toBeEmpty();
});

it('fails loudly when the source folder is missing', function () {
    config(['backup.source' => $this->source.'/tidak-ada']);

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('tidak ada')
        ->assertFailed();
});

it('mirrors the archive to off-site storage when one is configured', function () {
    Storage::fake('s3');
    config(['backup.offsite_disk' => 's3', 'backup.offsite_directory' => 'rakku-files']);
    File::put($this->source.'/receipts/struk.jpg', 'isi foto struk');

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('Salinan terkirim ke s3')
        ->assertSuccessful();

    $copies = Storage::disk('s3')->files('rakku-files');

    expect($copies)->toHaveCount(1)
        ->and(basename($copies[0]))->toStartWith(BackupFiles::ARCHIVE_PREFIX);
});

it('prunes off-site copies with the same retention limit', function () {
    Storage::fake('s3');
    config(['backup.offsite_disk' => 's3', 'backup.offsite_directory' => 'rakku-files']);
    File::put($this->source.'/receipts/struk.jpg', 'isi foto struk');

    collect(['2026-09-01-020000', '2026-09-02-020000'])->each(
        fn (string $timestamp) => Storage::disk('s3')->put('rakku-files/'.BackupFiles::ARCHIVE_PREFIX."{$timestamp}.zip", 'arsip lama'),
    );

    $this->artisan('app:backup-files', ['--keep' => 2])
        ->expectsOutputToContain('1 salinan lama dihapus')
        ->assertSuccessful();

    expect(Storage::disk('s3')->files('rakku-files'))->toHaveCount(2)
        ->and(Storage::disk('s3')->exists('rakku-files/'.BackupFiles::ARCHIVE_PREFIX.'2026-09-01-020000.zip'))->toBeFalse();
});

it('keeps the local archive even when the off-site copy fails', function () {
    config(['backup.offsite_disk' => 'disk-yang-tidak-ada']);
    File::put($this->source.'/receipts/struk.jpg', 'isi foto struk');

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('Arsip lokal selamat')
        ->assertFailed();

    expect(File::glob($this->destination.'/*.zip'))->toHaveCount(1);
});

it('deletes the oldest archives beyond the retention limit', function () {
    File::put($this->source.'/receipts/struk.jpg', 'isi foto struk');
    File::ensureDirectoryExists($this->destination);

    collect(['2026-09-01-020000', '2026-09-02-020000'])->each(
        fn (string $timestamp) => File::put($this->destination.'/'.BackupFiles::ARCHIVE_PREFIX."{$timestamp}.zip", 'arsip lama'),
    );

    $this->artisan('app:backup-files', ['--keep' => 2])
        ->expectsOutputToContain('1 arsip lama dihapus')
        ->assertSuccessful();

    expect(File::glob($this->destination.'/*.zip'))->toHaveCount(2)
        ->and(File::exists($this->destination.'/'.BackupFiles::ARCHIVE_PREFIX.'2026-09-01-020000.zip'))->toBeFalse()
        ->and(File::exists($this->destination.'/'.BackupFiles::ARCHIVE_PREFIX.'2026-09-02-020000.zip'))->toBeTrue();
});
