<?php

use App\Console\Commands\BackupFiles;
use Illuminate\Support\Facades\File;

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

it('leaves unfinished uploads out of the archive', function () {
    File::ensureDirectoryExists($this->source.'/livewire-tmp');
    File::put($this->source.'/livewire-tmp/setengah-jadi.png', 'unggahan batal');

    $this->artisan('app:backup-files')
        ->expectsOutputToContain('Belum ada file unggahan')
        ->assertSuccessful();

    expect(File::glob($this->destination.'/*.zip'))->toBeEmpty();
});

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
