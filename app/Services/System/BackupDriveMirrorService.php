<?php

namespace App\Services\System;

use App\Exceptions\PlatformBackupException;
use App\Models\BackupDrive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class BackupDriveMirrorService
{
    /**
     * @return array{ok: bool, message: string, resolved_path: ?string, free_bytes: ?int, total_bytes: ?int}
     */
    public function probePath(string $mountPath): array
    {
        try {
            $resolved = $this->resolveAndValidatePath($mountPath);

            return [
                'ok' => true,
                'message' => 'Path is writable.',
                'resolved_path' => $resolved,
                'free_bytes' => $this->diskFreeBytes($resolved),
                'total_bytes' => $this->diskTotalBytes($resolved),
            ];
        } catch (PlatformBackupException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'resolved_path' => null,
                'free_bytes' => null,
                'total_bytes' => null,
            ];
        }
    }

    /**
     * @return list<array{drive_id: int, drive_name: string, status: string, message: string, target: ?string}>
     */
    public function mirrorZipToEnabledDrives(string $zipPath): array
    {
        if (! is_file($zipPath)) {
            throw new PlatformBackupException('Backup ZIP not found for mirroring.');
        }

        $results = [];

        $drives = BackupDrive::query()
            ->where('enabled', true)
            ->where('mirror_on_backup', true)
            ->orderBy('name')
            ->get();

        foreach ($drives as $drive) {
            $results[] = $this->mirrorZipToDrive($drive, $zipPath);
        }

        return $results;
    }

    /**
     * @return array{drive_id: int, drive_name: string, status: string, message: string, target: ?string}
     */
    public function mirrorZipToDrive(BackupDrive $drive, string $zipPath): array
    {
        $base = [
            'drive_id' => $drive->id,
            'drive_name' => $drive->name,
            'status' => BackupDrive::STATUS_SKIPPED,
            'message' => '',
            'target' => null,
        ];

        if (! $drive->enabled) {
            $base['message'] = 'Drive is disabled.';

            return $base;
        }

        try {
            $targetDir = $this->driveBackupDirectory($drive);
            File::ensureDirectoryExists($targetDir, 0775, true);

            $target = $targetDir.'/'.basename($zipPath);
            if (! copy($zipPath, $target)) {
                throw new PlatformBackupException('Could not copy backup to drive.');
            }

            @chmod($target, 0664);

            $this->rotateOnDrive($drive, $targetDir);

            $drive->forceFill([
                'last_mirrored_at' => now(),
                'last_mirror_status' => BackupDrive::STATUS_OK,
                'last_mirror_error' => null,
                'last_mirror_size_bytes' => (int) filesize($target),
            ])->save();

            $base['status'] = BackupDrive::STATUS_OK;
            $base['message'] = 'Copied successfully.';
            $base['target'] = $target;

            return $base;
        } catch (\Throwable $e) {
            $message = $e instanceof PlatformBackupException ? $e->getMessage() : $e->getMessage();

            $drive->forceFill([
                'last_mirrored_at' => now(),
                'last_mirror_status' => BackupDrive::STATUS_FAILED,
                'last_mirror_error' => $message,
            ])->save();

            $base['status'] = BackupDrive::STATUS_FAILED;
            $base['message'] = $message;

            return $base;
        }
    }

    public function driveBackupDirectory(BackupDrive $drive): string
    {
        $root = $this->resolveAndValidatePath($drive->mount_path);
        $subdir = trim((string) config('backup.drive_subdirectory', 'isp-platform'), '/');

        return $subdir !== '' ? $root.'/'.$subdir : $root;
    }

    /**
     * @return list<array{label: string, created_at: string, size_bytes: int, path: string}>
     */
    public function listMirroredArchives(BackupDrive $drive): array
    {
        try {
            $dir = $this->driveBackupDirectory($drive);
        } catch (PlatformBackupException) {
            return [];
        }

        if (! is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach (glob($dir.'/isp-backup-*.zip') ?: [] as $zip) {
            $items[] = [
                'label' => basename($zip),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($zip)),
                'size_bytes' => (int) filesize($zip),
                'path' => $zip,
            ];
        }

        usort($items, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    private function rotateOnDrive(BackupDrive $drive, string $targetDir): void
    {
        $files = [];
        foreach (glob($targetDir.'/isp-backup-*.zip') ?: [] as $zip) {
            $files[] = [
                'path' => $zip,
                'mtime' => (int) filemtime($zip),
            ];
        }

        usort($files, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $max = $drive->effectiveMaxArchives();
        $cutoff = now()->subDays($drive->effectiveRetentionDays())->timestamp;

        foreach ($files as $index => $file) {
            $tooOld = $file['mtime'] < $cutoff;
            $overLimit = $index >= $max;

            if ($tooOld || $overLimit) {
                @unlink($file['path']);
            }
        }
    }

    private function resolveAndValidatePath(string $mountPath): string
    {
        $mountPath = trim($mountPath);

        if ($mountPath === '' || ! str_starts_with($mountPath, '/')) {
            throw new PlatformBackupException('Drive path must be an absolute path (e.g. /mnt/usb-backup).');
        }

        if (str_contains($mountPath, '..')) {
            throw new PlatformBackupException('Drive path cannot contain "..".');
        }

        $roots = config('backup.allowed_drive_roots', []);
        if ($roots !== []) {
            $allowed = false;
            foreach ($roots as $root) {
                if ($root === '' || ! str_starts_with($root, '/')) {
                    continue;
                }
                if ($mountPath === $root || str_starts_with($mountPath.'/', $root.'/')) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                throw new PlatformBackupException(
                    'Path must be under an allowed root: '.implode(', ', $roots).'. Set BACKUP_ALLOWED_DRIVE_ROOTS in .env to add more.',
                );
            }
        }

        $resolved = realpath($mountPath);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new PlatformBackupException('Drive path does not exist or is not a directory. Mount the disk first, then add the path.');
        }

        if (! is_writable($resolved)) {
            throw new PlatformBackupException('Drive path is not writable by the web server (www-data). Check mount permissions.');
        }

        $blocked = ['/', '/etc', '/usr', '/bin', '/sbin', '/boot', '/root', '/proc', '/sys', '/dev'];
        foreach ($blocked as $blockedPath) {
            if ($resolved === $blockedPath || str_starts_with($resolved.'/', $blockedPath.'/')) {
                throw new PlatformBackupException('This system path cannot be used as a backup drive.');
            }
        }

        $appRoot = realpath(base_path());
        if ($appRoot !== false) {
            $sensitive = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'];
            foreach ($sensitive as $segment) {
                $full = $appRoot.DIRECTORY_SEPARATOR.$segment;
                if ($resolved === $full || str_starts_with($resolved.DIRECTORY_SEPARATOR, $full.DIRECTORY_SEPARATOR)) {
                    throw new PlatformBackupException('Laravel application folders cannot be used as a backup drive.');
                }
            }

            $primaryBackup = realpath(app(PlatformBackupService::class)->backupRoot());
            if ($primaryBackup !== false && ($resolved === $primaryBackup || str_starts_with($resolved.DIRECTORY_SEPARATOR, $primaryBackup.DIRECTORY_SEPARATOR))) {
                throw new PlatformBackupException('Use a separate mount (USB/NFS), not the primary server backup folder.');
            }
        }

        return $resolved;
    }

    private function diskFreeBytes(string $path): ?int
    {
        $free = @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }

    private function diskTotalBytes(string $path): ?int
    {
        $total = @disk_total_space($path);

        return $total === false ? null : (int) $total;
    }
}
