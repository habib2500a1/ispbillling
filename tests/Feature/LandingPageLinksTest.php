<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class LandingPageLinksTest extends TestCase
{
    public function test_landing_view_includes_portal_and_app_links(): void
    {
        config(['portal.enabled' => true]);

        $html = view('landing.index', [
            'portalNotices' => collect(),
            'portalMarquee' => collect(),
            'company' => 'Test ISP',
            'tagline' => 'Fast internet',
            'phone' => null,
            'email' => null,
            'address' => null,
            'logo' => null,
            'packages' => collect(),
            'movieServers' => collect(),
            'adminUrl' => 'https://example.com/admin',
            'staffLoginUrl' => 'https://example.com/admin/login',
            'payUrl' => 'https://example.com/pay',
            'loginHubUrl' => 'https://example.com/login',
            'portalUrl' => 'https://example.com/login',
            'customerLoginUrl' => 'https://example.com/login/customer',
            'resellerLoginUrl' => 'https://example.com/reseller/login',
            'portalDashboardUrl' => 'https://example.com/portal',
            'signupUrl' => null,
            'appDownloadUrl' => 'https://example.com/downloads/isp-radiant.apk',
        ])->render();

        $this->assertStringContainsString('Sign in', $html);
        $this->assertStringContainsString('One place to sign in', $html);
        $this->assertStringContainsString('Mobile app', $html);
        $this->assertStringContainsString('/downloads/isp-radiant.apk', $html);
        $this->assertStringContainsString('https://example.com/login', $html);
    }
}
