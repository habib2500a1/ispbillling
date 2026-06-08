<?php

namespace App\Support;

final class IspOsStyles
{
    public const BUNDLE_FILE = 'isp-os-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/isp-os/01-tokens.css',
            'admin/isp-os/02-hub.css',
            'admin/isp-os/03-fault-timeline.css',
            'admin/isp-os/04-dependency-search.css',
            'admin/isp-os/05-mobile.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'isp-os', 'isp-os', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'isp-os',
            'isp-os',
            'ensureIspOsCss',
            self::BUNDLE_FILE,
        );
    }
}
