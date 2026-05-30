<?php

namespace App\Services\Mobile;

use App\Models\Package;
use App\Models\PortalNotice;
use App\Support\CompanyBranding;
use App\Support\MobileApkRelease;
use App\Support\MobileAppLinks;
use App\Support\PersonalMfsSetup;
use App\Support\ResellerBranding;

/**
 * Mobile app ↔ website sync: URLs and feature flags match the web portal/admin.
 */
class MobileConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $broadcast = config('broadcasting.default', 'log');

        $tenantId = $this->resolveTenantId();

        return [
            'app_name' => CompanyBranding::name(),
            'api_version' => 'v1',
            'app_version' => '2.6.0',
            'rcl_sms' => [
                'app_name' => 'RCL SMS',
                'company_name' => CompanyBranding::name(),
                'logo_url' => CompanyBranding::logoUrl(),
            ],
            'branding' => ResellerBranding::mobileBrandingPayload(),
            'notices' => $this->activeNotices($tenantId),
            'packages' => $this->websitePackages($tenantId),
            'phases' => [
                'phase_1' => 'live',
                'phase_2' => 'live',
                'phase_3' => 'live',
                'phase_4' => 'live',
            ],
            'links' => [
                'base' => $base,
                'pay' => $base.'/pay',
                'portal_login' => MobileAppLinks::portalLoginUrl() ?? $base.'/login',
                'landing' => MobileAppLinks::landingUrl(),
                'apk' => MobileAppLinks::downloadUrl(),
                'apk_mfs_verify' => MobileAppLinks::mfsVerifyDownloadUrl(),
                'apk_mfs_verify_update' => MobileAppLinks::mfsVerifyUpdateUrl(),
                'apk_mfs_verify_version' => MobileApkRelease::mfsVerify()['version_label'],
                'admin' => $base.'/admin',
                'admin_login' => MobileAppLinks::staffLoginUrl(),
                'personal_mfs' => PersonalMfsSetup::mobileConfigLinks(),
            ],
            'staff_paths' => $this->staffPaths($base),
            'features' => [
                'bkash' => (bool) config('bkash.enabled'),
                'portal' => (bool) config('portal.enabled', true),
                'customer_tickets' => true,
                'staff_collection' => true,
                'push_fcm' => (bool) config('mobile.fcm_enabled'),
                'realtime_ws' => $broadcast !== 'log',
                'offline_sync' => true,
                'ai_assistant' => true,
                'anomaly_detection' => true,
                'network_control' => true,
                'mfs_sms_staff' => (bool) config('mfs_personal.sms_ingest.enabled', false),
                'biometric_login' => true,
                'ssl_pinning' => (bool) config('mobile.ssl_pinning', false),
                'crash_reporting' => (bool) config('mobile.crash_reporting', true),
            ],
            'apps' => [
                'customer' => [
                    'status' => 'live',
                    'modules' => ['dashboard', 'billing', 'usage', 'onu', 'tickets', 'packages', 'pay', 'ai'],
                ],
                'collector' => [
                    'status' => 'live',
                    'modules' => ['search', 'collect', 'wallet', 'expense', 'settlement', 'offline_sync'],
                ],
                'technician' => [
                    'status' => 'live',
                    'modules' => ['field_visits', 'installation', 'gps', 'photo_upload'],
                ],
                'noc' => [
                    'status' => 'live',
                    'modules' => ['dashboard', 'online_clients', 'alerts', 'onu_weak'],
                ],
                'admin' => [
                    'status' => 'live',
                    'modules' => ['dashboard', 'tickets', 'tasks', 'billing_kpi', 'network_control'],
                ],
            ],
            'ticket' => [
                'departments' => array_keys(\App\Models\SupportTicket::DEPARTMENTS),
                'priorities' => array_keys(\App\Models\SupportTicket::PRIORITIES),
                'defaults' => [
                    'department' => 'technical_support',
                    'priority' => 'medium',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function staffPaths(string $base): array
    {
        $admin = $base.'/admin';

        return [
            'billing' => $admin,
            'collect' => $admin.'/bill-collection',
            'monitoring' => $admin.'/online-clients',
            'add_client' => $admin.'/customers/create',
            'clients' => $admin.'/customers',
            'tickets' => $admin.'/support-tickets',
            'approval' => $admin.'/bill-collection',
            'expense' => $admin.'/collector-cash-hub',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveTenantId(): ?int
    {
        try {
            return \App\Support\TenantResolver::requiredTenantId();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Active packages shown on website/portal — same list the app should use.
     *
     * @return list<array<string, mixed>>
     */
    private function websitePackages(?int $tenantId): array
    {
        if ($tenantId === null || ! \Illuminate\Support\Facades\Schema::hasTable('packages')) {
            return [];
        }

        return Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('show_on_website', true)
            ->orderBy('price_monthly')
            ->get(['id', 'name', 'download_mbps', 'upload_mbps', 'price_monthly', 'mikrotik_profile_name'])
            ->map(fn (Package $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'download_mbps' => $p->download_mbps,
                'upload_mbps' => $p->upload_mbps,
                'price_monthly' => (float) $p->price_monthly,
                'mikrotik_profile' => $p->mikrotik_profile_name,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeNotices(?int $tenantId): array
    {
        if ($tenantId === null || ! \Illuminate\Support\Facades\Schema::hasTable('portal_notices')) {
            return [];
        }

        try {
            return PortalNotice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->active()
            ->where(function ($q): void {
                $q->where('show_on_portal', true)->orWhere('show_on_landing', true);
            })
            ->ordered()
            ->limit(5)
            ->get(['id', 'title', 'body'])
            ->map(fn (PortalNotice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
            ])
            ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
