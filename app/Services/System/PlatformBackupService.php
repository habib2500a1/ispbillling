<?php

namespace App\Services\System;

use App\Exceptions\PlatformBackupException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

final class PlatformBackupService
{
    /**
     * @return array{stamp: string, directory: string, zip: ?string, manifest: array<string, mixed>}
     */
    public function create(bool $packageZip = true): array
    {
        $this->ensureBackupRootWritable();

        $stamp = now()->format('Y-m-d_His');
        $directory = $this->backupRoot().'/'.$stamp;
        File::ensureDirectoryExists($directory);

        $dbDump = $directory.'/database.dump';
        $this->dumpDatabase($dbDump);

        if (config('backup.include_storage_app', true)) {
            $this->archiveStorageApp($directory.'/storage-app.tgz');
        }

        $manifest = $this->buildManifest($stamp);
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zipPath = null;
        if ($packageZip) {
            $zipPath = $this->backupRoot().'/isp-backup-'.$stamp.'.zip';
            $this->zipDirectory($directory, $zipPath);
        }

        $this->rotateOldBackups();

        return [
            'stamp' => $stamp,
            'directory' => $directory,
            'zip' => $zipPath,
            'manifest' => $manifest,
        ];
    }

    /**
     * @return list<array{id: string, label: string, created_at: string, size_bytes: int, zip_path: ?string, has_database: bool}>
     */
    public function listArchives(): array
    {
        $root = $this->backupRoot();
        if (! is_dir($root)) {
            return [];
        }

        $items = [];

        foreach (glob($root.'/isp-backup-*.zip') ?: [] as $zip) {
            $base = basename($zip);
            $id = Str::after($base, 'isp-backup-');
            $id = Str::beforeLast($id, '.zip');
            $items[$id] = [
                'id' => $id,
                'label' => $base,
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($zip)),
                'size_bytes' => (int) filesize($zip),
                'zip_path' => $zip,
                'has_database' => true,
            ];
        }

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $id = basename($dir);
            if (isset($items[$id]) || ! preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $id)) {
                continue;
            }
            $items[$id] = [
                'id' => $id,
                'label' => 'isp-backup-'.$id.' (folder)',
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($dir)),
                'size_bytes' => $this->directorySize($dir),
                'zip_path' => null,
                'has_database' => is_file($dir.'/database.dump') || is_file($dir.'/database.sqlite'),
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_values($items);
    }

    public function resolveZipPath(string $id): string
    {
        $zip = $this->backupRoot().'/isp-backup-'.$id.'.zip';
        if (! is_file($zip)) {
            throw new PlatformBackupException('Backup archive not found.');
        }

        return $zip;
    }

    public function deleteArchive(string $id): void
    {
        $zip = $this->backupRoot().'/isp-backup-'.$id.'.zip';
        if (is_file($zip)) {
            unlink($zip);
        }

        $dir = $this->backupRoot().'/'.$id;
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    /**
     * @return array{pre_restore_stamp: string, restored_from: string}
     */
    public function restoreFromZip(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new PlatformBackupException('Upload file missing.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new PlatformBackupException('PHP Zip extension is required for restore.');
        }

        $temp = storage_path('app/backup-restore-'.Str::random(8));
        File::ensureDirectoryExists($temp);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new PlatformBackupException('Could not open backup ZIP.');
            }
            $zip->extractTo($temp);
            $zip->close();

            $workDir = $this->findExtractedRoot($temp);
            $manifest = $this->readManifest($workDir);

            $safety = $this->create(packageZip: true);

            Artisan::call('down', ['--retry' => 60]);

            try {
                $this->restoreDatabaseFromWorkDir($workDir, $manifest);
                $this->restoreStorageFromWorkDir($workDir);
            } finally {
                Artisan::call('up');
            }

            Artisan::call('optimize:clear');

            return [
                'pre_restore_stamp' => $safety['stamp'],
                'restored_from' => (string) ($manifest['stamp'] ?? 'unknown'),
            ];
        } finally {
            File::deleteDirectory($temp);
        }
    }

    public function backupRoot(): string
    {
        return storage_path('app/'.trim(config('backup.path', 'backups'), '/'));
    }

    public function ensureBackupRootWritable(): void
    {
        $root = $this->backupRoot();
        if (is_dir($root) && ! is_writable($root)) {
            throw new PlatformBackupException(
                'Backup folder is not writable by the web server: '.$root.'. Run: sudo chown -R www-data:www-data '.$root,
            );
        }

        if (! is_dir($root)) {
            try {
                File::ensureDirectoryExists($root, 0775, true);
            } catch (\Throwable $e) {
                throw new PlatformBackupException(
                    'Could not create backup folder: '.$root.' — '.$e->getMessage(),
                );
            }
        }
    }

    private function dumpDatabase(string $targetPath): void
    {
        $driver = config('database.default');

        if ($driver === 'pgsql') {
            $this->dumpPostgres($targetPath);

            return;
        }

        if ($driver === 'sqlite') {
            $source = config('database.connections.sqlite.database');
            if (! is_file($source)) {
                throw new PlatformBackupException('SQLite database file not found.');
            }
            if (! copy($source, $targetPath.'.sqlite')) {
                throw new PlatformBackupException('Could not copy SQLite database.');
            }

            return;
        }

        throw new PlatformBackupException('Backup supports PostgreSQL and SQLite only.');
    }

    private function dumpPostgres(string $targetPath): void
    {
        $cfg = config('database.connections.pgsql');
        $binary = (string) config('backup.pg_dump_binary', 'pg_dump');

        $process = new Process([
            $binary,
            '-h', (string) $cfg['host'],
            '-p', (string) $cfg['port'],
            '-U', (string) $cfg['username'],
            '-Fc',
            '-f', $targetPath,
            (string) $cfg['database'],
        ], null, [
            'PGPASSWORD' => (string) ($cfg['password'] ?? ''),
        ]);

        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PlatformBackupException('pg_dump failed: '.trim($process->getErrorOutput()));
        }
    }

    private function archiveStorageApp(string $targetPath): void
    {
        $appPath = storage_path('app');
        $exclude = basename($this->backupRoot());

        $process = new Process([
            'tar',
            '-czf',
            $targetPath,
            '--exclude='.$exclude,
            '-C',
            storage_path(),
            'app',
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PlatformBackupException('Storage archive failed: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManifest(string $stamp): array
    {
        return [
            'format' => 1,
            'stamp' => $stamp,
            'app' => config('app.name'),
            'url' => config('app.url'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'db_driver' => config('database.default'),
            'db_database' => config('database.connections.'.config('database.default').'.database'),
            'created_at' => now()->toIso8601String(),
            'includes_storage_app' => (bool) config('backup.include_storage_app', true),
        ];
    }

    private function zipDirectory(string $sourceDir, string $zipPath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new PlatformBackupException('PHP Zip extension is required.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PlatformBackupException('Could not create ZIP archive.');
        }

        $sourceDir = rtrim($sourceDir, '/');
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }
            $relative = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relative);
        }

        $zip->close();
    }

    private function rotateOldBackups(): void
    {
        $archives = $this->listArchives();
        $max = (int) config('backup.max_archives', 20);
        $cutoff = now()->subDays((int) config('backup.retention_days', 14));

        foreach ($archives as $index => $archive) {
            $tooOld = strtotime($archive['created_at']) < $cutoff->timestamp;
            $overLimit = $index >= $max;

            if ($tooOld || $overLimit) {
                $this->deleteArchive($archive['id']);
            }
        }
    }

    private function findExtractedRoot(string $temp): string
    {
        if (is_file($temp.'/manifest.json')) {
            return $temp;
        }

        foreach (glob($temp.'/*', GLOB_ONLYDIR) ?: [] as $subdir) {
            if (is_file($subdir.'/manifest.json')) {
                return $subdir;
            }
        }

        throw new PlatformBackupException('Invalid backup: manifest.json missing.');
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $workDir): array
    {
        $raw = file_get_contents($workDir.'/manifest.json');
        if ($raw === false) {
            throw new PlatformBackupException('Could not read manifest.');
        }

        $manifest = json_decode($raw, true);
        if (! is_array($manifest)) {
            throw new PlatformBackupException('Invalid manifest JSON.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function restoreDatabaseFromWorkDir(string $workDir, array $manifest): void
    {
        $driver = (string) ($manifest['db_driver'] ?? config('database.default'));

        if ($driver === 'pgsql' && is_file($workDir.'/database.dump')) {
            $this->restorePostgres($workDir.'/database.dump');

            return;
        }

        if ($driver === 'sqlite' && is_file($workDir.'/database.dump.sqlite')) {
            $target = config('database.connections.sqlite.database');
            DB::disconnect();
            if (! copy($workDir.'/database.dump.sqlite', $target)) {
                throw new PlatformBackupException('Could not restore SQLite database.');
            }

            return;
        }

        if (is_file($workDir.'/database.dump')) {
            $this->restorePostgres($workDir.'/database.dump');

            return;
        }

        throw new PlatformBackupException('No database dump found in backup.');
    }

    private function restorePostgres(string $dumpPath): void
    {
        $cfg = config('database.connections.pgsql');
        $binary = (string) config('backup.pg_restore_binary', 'pg_restore');

        DB::disconnect();

        $process = new Process([
            $binary,
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-acl',
            '-h', (string) $cfg['host'],
            '-p', (string) $cfg['port'],
            '-U', (string) $cfg['username'],
            '-d', (string) $cfg['database'],
            $dumpPath,
        ], null, [
            'PGPASSWORD' => (string) ($cfg['password'] ?? ''),
        ]);

        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PlatformBackupException('pg_restore failed: '.trim($process->getErrorOutput()));
        }
    }

    private function restoreStorageFromWorkDir(string $workDir): void
    {
        $archive = $workDir.'/storage-app.tgz';
        if (! is_file($archive)) {
            return;
        }

        $process = new Process([
            'tar',
            '-xzf',
            $archive,
            '-C',
            storage_path(),
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new PlatformBackupException('Storage restore failed: '.trim($process->getErrorOutput()));
        }
    }

    private function directorySize(string $dir): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }
}
