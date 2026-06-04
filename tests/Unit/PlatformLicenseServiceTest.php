<?php

namespace Tests\Unit;

use App\Services\Platform\PlatformLicenseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PlatformLicenseServiceTest extends TestCase
{
    private string $publicPath;

    private string $privatePath;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = storage_path('framework/testing/license-'.uniqid('', true));
        File::ensureDirectoryExists($dir);
        $this->publicPath = $dir.'/public.pem';
        $this->privatePath = $dir.'/private.pem';

        config([
            'isp.license.public_key_path' => $this->publicPath,
            'isp.license.private_key_path' => $this->privatePath,
        ]);

        Artisan::call('isp:license:generate-keys', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if (isset($this->publicPath)) {
            File::deleteDirectory(dirname($this->publicPath));
        }

        parent::tearDown();
    }

    public function test_issue_and_validate_license(): void
    {
        Artisan::call('isp:issue-license', [
            'domain' => 'bill.example.com',
            '--expires' => now()->addYear()->toDateString(),
            '--deployment' => PlatformLicenseService::DEPLOYMENT_ON_PREMISE,
        ]);

        $output = Artisan::output();
        preg_match('/ISP_LICENSE_KEY=(.+)/', $output, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        config([
            'isp.deployment_mode' => PlatformLicenseService::DEPLOYMENT_ON_PREMISE,
            'isp.license.enforce' => true,
            'isp.license.key' => trim($matches[1]),
        ]);

        $service = app(PlatformLicenseService::class);
        $check = $service->validate('bill.example.com');

        $this->assertTrue($check['valid'], $check['message']);
    }

    public function test_saas_skips_enforcement(): void
    {
        config([
            'isp.deployment_mode' => PlatformLicenseService::DEPLOYMENT_SAAS,
            'isp.license.enforce' => false,
            'isp.license.key' => '',
        ]);

        $service = app(PlatformLicenseService::class);
        $this->assertFalse($service->isEnforced());
        $this->assertTrue($service->validate('any.host.com')['valid']);
    }
}
