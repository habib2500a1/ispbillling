<?php

namespace Tests\Feature;

use App\Support\TrustedAppUrl;
use Tests\TestCase;

class TrustedAppUrlTest extends TestCase
{
    public function test_merge_previous_urls_when_domain_changes(): void
    {
        $merged = TrustedAppUrl::mergePreviousUrls(
            'https://old.example.com',
            'https://new.example.com',
            '',
        );

        $this->assertSame('https://old.example.com', $merged);
    }

    public function test_allowed_hosts_include_previous_urls(): void
    {
        config(['app.url' => 'https://new.example.com']);
        putenv('APP_PREVIOUS_URLS=https://old.example.com,https://legacy.example.com');

        $hosts = TrustedAppUrl::allowedHosts();

        $this->assertContains('new.example.com', $hosts);
        $this->assertContains('old.example.com', $hosts);
        $this->assertContains('legacy.example.com', $hosts);
    }
}
