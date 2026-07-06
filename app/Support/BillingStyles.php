<?php

namespace App\Support;

/**
 * Modular billing & invoice UI CSS — edit public/css/admin/billing/.
 */
final class BillingStyles
{
    public const BUNDLE_FILE = 'billing-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/billing/01-tokens.css',
            'admin/billing/02-hub-dashboard.css',
            'admin/billing/03-invoice-list.css',
            'admin/billing/04-invoice-detail.css',
            'admin/billing/05-notices.css',
            'admin/billing/06-sidebar.css',
            'admin/billing/07-mobile.css',
            'admin/billing/08-light-mode.css',
            'admin/billing/09-dark-mode.css',
            'admin/billing/10-hub-v3.css',
            'admin/billing/11-collection-desk-v3.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-billing', 'billing', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-billing',
            'billing',
            'ensureBillingCss',
            self::BUNDLE_FILE,
        );
    }
}
