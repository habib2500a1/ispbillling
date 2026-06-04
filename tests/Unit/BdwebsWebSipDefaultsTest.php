<?php

namespace Tests\Unit;

use App\Support\BdwebsWebSipDefaults;
use Tests\TestCase;

class BdwebsWebSipDefaultsTest extends TestCase
{
    public function test_builds_candidates_from_domain(): void
    {
        $uris = BdwebsWebSipDefaults::wssCandidatesFor('sip17.bdwebs.com');

        $this->assertContains('wss://sip17.bdwebs.com:7443/ws', $uris);
        $this->assertNotEmpty($uris);
    }

    public function test_explicit_uri_first(): void
    {
        $uris = BdwebsWebSipDefaults::resolveWssUris(
            'wss://custom.example/ws',
            'sip17.bdwebs.com',
            '202.40.176.2',
        );

        $this->assertSame('wss://custom.example/ws', $uris[0]);
    }

    public function test_includes_ip_wss_candidates(): void
    {
        $uris = BdwebsWebSipDefaults::resolveWssUris(null, null, '202.40.176.2');

        $this->assertContains('wss://202.40.176.2:7443/ws', $uris);
    }
}
