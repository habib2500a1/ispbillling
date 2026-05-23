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
}
