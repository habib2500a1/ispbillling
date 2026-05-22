<?php

namespace Tests\Unit;

use App\Support\MobileAppLinks;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class MobileAppLinksTest extends TestCase
{
    public function test_mfs_verify_prefers_server_apk_over_github_env(): void
    {
        $path = public_path('downloads/isp-mfs-verify.apk');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_repeat('x', 2048));

        config([
            'mobile.mfs_verify_apk_url' => 'https://github.com/example/releases/download/v1/app.apk',
            'mobile.use_github_releases' => true,
            'app.url' => 'https://bill.example.test',
        ]);

        try {
            $url = MobileAppLinks::mfsVerifyDownloadUrl();

            $this->assertStringContainsString('/downloads/isp-mfs-verify.apk', $url);
            $this->assertSame('server', MobileAppLinks::mfsVerifySource());
        } finally {
            @unlink($path);
        }
    }
}
