<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\System\GoogleDriveBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::putValue(GoogleDriveBackupService::SETTING_CLIENT_ID, 'test-client-id');
        AppSetting::putValue(GoogleDriveBackupService::SETTING_CLIENT_SECRET, 'test-client-secret');
        AppSetting::putValue(GoogleDriveBackupService::SETTING_REFRESH_TOKEN, 'refresh-token-xyz');
        AppSetting::putValue(GoogleDriveBackupService::SETTING_ENABLED, '1');
        AppSetting::putValue(GoogleDriveBackupService::SETTING_FOLDER_ID, 'folder-abc');
    }

    public function test_upload_backup_zip_to_google_drive(): void
    {
        $zip = storage_path('app/test-google-drive.zip');
        file_put_contents($zip, 'zip-content-test');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-123'], 200),
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable' => Http::response('', 200, [
                'Location' => 'https://upload.example/resumable',
            ]),
            'https://upload.example/resumable' => Http::response(['id' => 'file-999', 'name' => 'test.zip'], 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::response(['files' => []], 200),
        ]);

        $service = app(GoogleDriveBackupService::class);
        $result = $service->uploadBackupZip($zip);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('file-999', $result['file_id']);

        @unlink($zip);
    }

    public function test_build_auth_url_contains_client_id(): void
    {
        $url = app(GoogleDriveBackupService::class)->buildAuthUrl();

        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('access_type=offline', $url);
    }
}
