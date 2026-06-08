<?php

namespace App\Support;

/**
 * Modular support & ticket UI CSS — edit public/css/admin/support/.
 */
final class SupportStyles
{
    public const BUNDLE_FILE = 'support-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/support/01-tokens.css',
            'admin/support/02-hub-dashboard.css',
            'admin/support/03-ticket-list.css',
            'admin/support/04-ticket-detail.css',
            'admin/support/05-timeline.css',
            'admin/support/07-gis-preview.css',
            'admin/support/06-sidebar.css',
            'admin/support/08-light-mode.css',
            'admin/support/09-dark-mode.css',
            'admin/support/10-hub-v3.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-support', 'support', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-support',
            'support',
            'ensureSupportCss',
            self::BUNDLE_FILE,
        );
    }
}
