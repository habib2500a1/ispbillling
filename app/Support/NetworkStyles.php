<?php

namespace App\Support;

/**
 * Modular Router / MikroTik NOC UI CSS — edit public/css/admin/network/.
 */
final class NetworkStyles
{
    public const BUNDLE_FILE = 'network-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/network/01-tokens.css',
            'admin/network/02-hub-dashboard.css',
            'admin/network/03-routers-list.css',
            'admin/network/04-router-profile.css',
            'admin/network/05-online-clients.css',
            'admin/network/06-bandwidth-monitor.css',
            'admin/network/07-monitoring-pages.css',
            'admin/network/08-settings-import.css',
            'admin/network/09-sidebar.css',
            'admin/network/10-light-dark-mobile.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-network', 'network', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-network',
            'network',
            'ensureNetworkCss',
            self::BUNDLE_FILE,
        );
    }
}
