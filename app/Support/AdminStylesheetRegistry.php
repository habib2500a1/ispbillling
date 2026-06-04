<?php

namespace App\Support;

/**
 * Central map: which CSS bundles load where (edit paths in each *Styles class).
 */
final class AdminStylesheetRegistry
{
    /**
     * @return array<string, string> Bundle key => human label
     */
    public static function bundleLabels(): array
    {
        return [
            'admin_saas' => 'All admin pages (design-system)',
            'clients_directory' => 'Clients list (/admin/subscribers …)',
            'subscriber_view' => 'Subscriber 360 view page',
            'route_hubs' => 'Per-page hub CSS (AdminRouteAssets::stylesheetMap)',
        ];
    }

    /**
     * All modular bundles to verify on deploy.
     *
     * @return list<list<string>>
     */
    public static function allModuleLists(): array
    {
        return [
            AdminSaasStyles::modules(),
            ClientsDirectoryStyles::modules(),
            SubscriberViewStyles::modules(),
        ];
    }

    /**
     * @return list<string> Missing module files
     */
    public static function missingModules(): array
    {
        $missing = [];

        foreach (self::allModuleLists() as $modules) {
            foreach (StylesheetModules::missing($modules) as $file) {
                $missing[] = $file;
            }
        }

        return array_values(array_unique($missing));
    }

}
