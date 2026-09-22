<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

#[Signature('app:backup-files {--keep= : Jumlah arsip terakhir yang disimpan}')]
#[Description('Arsipkan file unggahan (foto struk & hasil export) ke folder backup')]
class BackupFiles extends Command
{
    public const string ARCHIVE_PREFIX = 'rakku-files-';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = (string) config('backup.source');

        if (! File::isDirectory($source)) {
            $this->error("Folder {$source} tidak ada, tidak ada yang bisa diarsipkan.");

            return self::FAILURE;
        }

        $source = (string) realpath($source);

        $files = $this->filesIn($source);

        if ($files === []) {
            $this->info('Belum ada file unggahan, arsip tidak dibuat.');

            return self::SUCCESS;
        }

        $destination = (string) config('backup.destination');
        File::ensureDirectoryExists($destination);

        $archive = $destination.DIRECTORY_SEPARATOR.self::ARCHIVE_PREFIX.now()->format('Y-m-d-His').'.zip';

        if (! $this->compress($files, $source, $archive)) {
            $this->error("Gagal menulis arsip ke {$archive}.");

            return self::FAILURE;
        }

        $deletedCount = $this->pruneOldArchives($destination);

        $this->info(sprintf(
            '%d file (%s) diarsipkan ke %s.%s',
            count($files),
            $this->humanSize((int) File::size($archive)),
            $archive,
            $deletedCount > 0 ? " {$deletedCount} arsip lama dihapus." : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Daftar file di dalam folder sumber, termasuk subfolder, kecuali folder yang dikecualikan.
     *
     * @return list<string>
     */
    private function filesIn(string $source): array
    {
        $excluded = collect(config('backup.excluded_directories'))
            ->map(fn (string $directory): string => $source.DIRECTORY_SEPARATOR.$directory);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $excluded->contains(fn (string $directory): bool => str_starts_with($file->getPath(), $directory))) {
                $files[] = $file->getRealPath();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param  list<string>  $files
     */
    private function compress(array $files, string $source, string $archive): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach ($files as $file) {
            $relativePath = ltrim(str_replace($source, '', $file), '\\/');

            $zip->addFile($file, str_replace('\\', '/', $relativePath));
        }

        return $zip->close();
    }

    /**
     * Sisakan arsip terbaru sebanyak batas retensi.
     */
    private function pruneOldArchives(string $destination): int
    {
        $keep = max(1, (int) ($this->option('keep') ?? config('backup.keep')));

        $archives = collect(File::glob($destination.DIRECTORY_SEPARATOR.self::ARCHIVE_PREFIX.'*.zip'))
            ->sortDesc()
            ->values();

        $obsolete = $archives->slice($keep);

        File::delete($obsolete->all());

        return $obsolete->count();
    }

    private function humanSize(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? round($bytes / 1024, 1).' KB'
            : round($bytes / 1024 / 1024, 1).' MB';
    }
}
