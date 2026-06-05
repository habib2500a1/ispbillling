<?php

namespace Tests\Unit;

use App\Support\OltManagementHelper;
use Tests\TestCase;

class OltManagementHelperTest extends TestCase
{
    public function test_normalizes_pasted_management_url(): void
    {
        $this->assertSame(
            '103.29.127.94',
            OltManagementHelper::normalizeManagementIp('http://103.29.127.94:8506'),
        );
    }

    public function test_default_aveis_web_url_includes_port_suffix(): void
    {
        $url = OltManagementHelper::defaultAveisWebUrl('103.29.127.94');

        $this->assertStringStartsWith('103.29.127.94:', $url);
        $this->assertMatchesRegularExpression('/:\d+$/', $url);
    }

    public function test_web_ui_url_uses_https_for_port_443(): void
    {
        $olt = new \App\Models\Device([
            'type' => 'olt',
            'olt_driver' => 'aveis_epon',
            'meta' => ['olt_web_url' => '103.29.127.94:443'],
        ]);

        $this->assertSame('https://103.29.127.94:443', OltManagementHelper::webUiUrl($olt));
    }

    public function test_normalize_web_url_strips_scheme_and_path(): void
    {
        $this->assertSame(
            '103.29.127.94:8506',
            OltManagementHelper::normalizeWebUrl('http://103.29.127.94:8506/'),
        );
    }

    public function test_web_ui_url_uses_http_for_aveis_port_8506(): void
    {
        $olt = new \App\Models\Device([
            'type' => 'olt',
            'olt_driver' => 'aveis_epon',
            'meta' => ['olt_web_url' => '103.29.127.94:8506'],
        ]);

        $this->assertSame('http://103.29.127.94:8506', OltManagementHelper::webUiUrl($olt));
    }
}
