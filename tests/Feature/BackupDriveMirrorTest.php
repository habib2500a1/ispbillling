<?php

namespace Tests\Feature;

use App\Models\BackupDrive;
use App\Services\System\BackupDriveMirrorService;
use App\Services\System\PlatformBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class BackupDriveMirrorTest extends TestCase
{
    use RefreshDatabase;

    private string $primaryRoot;

    private string $externalRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryRoot = storage_path('app/backups-test-primary');
        $this->externalRoot = rtrim(sys_get_temp_dir(), '/').'/isp-backup-drive-test';

        File::deleteDirectory($this->primaryRoot);
        File::deleteDirectory($this->externalRoot);
        File::ensureDirectoryExists($this->primaryRoot);
        File::ensureDirectoryExists($this->externalRoot);

        config([
            'backup.path' => 'backups-test-primary',
            'backup.allowed_drive_roots' => [rtrim(sys_get_temp_dir(), '/')],
            'backup.include_storage_app' => false,
            'backup.drive_subdirectory' => 'isp-platform',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->primaryRoot);
        File::deleteDirectory($this->externalRoot);

        parent::tearDown();
    }

    public function test_probe_rejects_path_outside_allowed_roots(): void
    {
        config(['backup.allowed_drive_roots' => ['/mnt']]);

        $probe = app(BackupDriveMirrorService::class)->probePath($this->externalRoot);

        $this->assertFalse($probe['ok']);
        $this->assertStringContainsString('allowed root', strtolower($probe['message']));
    }

    public function test_mirror_copies_zip_to_external_drive_and_rotates(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Zip extension not available.');
        }

        $dbFile = storage_path('app/testing-backup-drive.sqlite');
        if (is_file($dbFile)) {
            unlink($dbFile);
        }
        touch($dbFile);
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $dbFile]);

        $this->artisan('migrate', ['--force' => true]);

        $drive = BackupDrive::query()->create([
            'name' => 'Test USB',
            'mount_path' => $this->externalRoot,
            'enabled' => true,
            'mirror_on_backup' => true,
            'max_archives' => 2,
        ]);

        $service = app(PlatformBackupService::class);
        $first = $service->create();
        $this->assertFileExists($first['zip']);
        $this->assertCount(1, $first['mirror_results']);
        $this->assertSame(BackupDrive::STATUS_OK, $first['mirror_results'][0]['status']);

        $targetDir = app(BackupDriveMirrorService::class)->driveBackupDirectory($drive);
        $mirrored = $targetDir.'/'.basename((string) $first['zip']);
        $this->assertFileExists($mirrored);

        sleep(1);
        $service->create();
        $third = $service->create();

        $onDrive = glob($targetDir.'/isp-backup-*.zip') ?: [];
        $this->assertLessThanOrEqual(2, count($onDrive));
        $this->assertFileExists($targetDir.'/'.basename((string) $third['zip']));
    }
}
