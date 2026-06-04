<?php

namespace App\Support;

/**
 * Modular clients directory CSS — edit public/css/admin/clients-directory/.
 */
final class ClientsDirectoryStyles
{
    public const BUNDLE_FILE = 'clients-directory-pro.css';

    /**
     * @return list<string>
     */
    public static function modules(): array
    {
        return [
            'admin/clients-directory/01-page-shell.css',
            'admin/clients-directory/02-chrome-toolbar.css',
            'admin/clients-directory/03-table.css',
            'admin/clients-directory/04-due-page.css',
            'admin/clients-directory/05-vip-page.css',
        ];
    }

    public static function version(): int
    {
        return StylesheetModules::version(self::modules(), self::BUNDLE_FILE);
    }

    public static function html(): string
    {
        return StylesheetModules::html(self::modules(), 'clients-directory', 'clients-directory', self::BUNDLE_FILE);
    }

    public static function navigatedScript(): string
    {
        return StylesheetModules::navigatedScript(
            self::modules(),
            'clients-directory',
            'clients-directory',
            'ensureClientsDirectoryCss',
            self::BUNDLE_FILE,
        );
    }

}
