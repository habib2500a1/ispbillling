<?php

namespace Tests\Feature;

use App\Services\System\PlatformBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PlatformBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $root = storage_path('app/backups');
        if (is_dir($root)) {
            File::deleteDirectory($root);
        }

        parent::tearDown();
    }

    public function test_sqlite_backup_zip_and_restore_roundtrip(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Zip extension not available.');
        }

        $dbFile = storage_path('app/testing-backup.sqlite');
        if (is_file($dbFile)) {
            unlink($dbFile);
        }
        touch($dbFile);
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $dbFile]);
        config(['backup.include_storage_app' => false]);

        $this->artisan('migrate', ['--force' => true]);

        $service = app(PlatformBackupService::class);
        $created = $service->create();
        $this->assertFileExists($created['zip']);

        $archives = $service->listArchives();
        $this->assertNotEmpty($archives);

        $result = $service->restoreFromZip($created['zip']);
        $this->assertArrayHasKey('pre_restore_stamp', $result);
    }
}
