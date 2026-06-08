<?php

namespace App\Support;

/**
 * Modular OLT / GPON operations UI CSS — edit public/css/admin/olt/.
 */
final class OltStyles
{
    public const BUNDLE_FILE = 'olt-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/olt/01-tokens.css',
            'admin/olt/02-hub-operations.css',
            'admin/olt/03-optical-noc-shell.css',
            'admin/olt/04-signal-quality.css',
            'admin/olt/05-pon-faults.css',
            'admin/olt/06-olt-list-profile.css',
            'admin/olt/07-topology-insights.css',
            'admin/olt/08-mobile.css',
            'admin/olt/09-light-dark.css',
        ];
    }

    /**
     * Legacy monoliths merged into bundle by concat-olt-css.sh
     *
     * @return list<string>
     */
    public static function legacyBundles(): array
    {
        return [
            'olt-hub-pro.css',
            'optical-noc.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(
            array_merge(self::modules(), self::legacyBundles()),
            self::BUNDLE_FILE,
        );
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-olt', 'olt', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-olt',
            'olt',
            'ensureOltCss',
            self::BUNDLE_FILE,
        );
    }
}
