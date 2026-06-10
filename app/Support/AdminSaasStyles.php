<?php

namespace App\Support;

/**
 * Modular admin SaaS styles — edit files under public/css/admin/saas/.
 */
final class AdminSaasStyles
{
    public const BUNDLE_FILE = 'admin-saas.css';

    /** Bundle includes admin-utilities + admin-responsive (fewer HTTP requests). */
    public static function bundleIncludesExtras(): bool
    {
        return self::BUNDLE_FILE === 'admin-saas.css'
            && StylesheetModules::shouldBundle()
            && is_file(public_path('css/'.self::BUNDLE_FILE));
    }

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/saas/01-tokens.css',
            'admin/saas/02-sidebar.css',
            'admin/saas/03-dashboard-widgets.css',
            'admin/saas/04-analytics-blocks.css',
            'admin/saas/05-mobile-dock.css',
            'admin/saas/06-hubs-pages.css',
            'admin/saas/07-tables-subscribers.css',
            'admin/saas/08-dashboard-ops.css',
            'admin/saas/09-forms-details.css',
            'admin/saas/10-filament-overrides.css',
            'admin/saas/11-subscriber-view-legacy.css',
            'admin/saas/12-dashboard-home.css',
            'admin/saas/13-dashboard-insights.css',
            'admin/saas/14-light-mode-global.css',
            'admin/saas/15-dashboard-v2-zones.css',
            'admin/saas/16-subscriber-location-map.css',
        ];
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-admin-saas', 'admin-saas', self::BUNDLE_FILE);
    }

}
