<?php

namespace App\Support;

/**
 * Load hub/page CSS only on matching Filament routes (keeps global admin head minimal).
 */
final class AdminRouteAssets
{
    /**
     * @return array<string, list<string>>
     */
    public static function stylesheetMap(): array
    {
        return [
            'filament.admin.pages.clients-hub' => ['clients-hub-pro.css'],
            'filament.admin.pages.subscriber-lists-hub' => ['subscriber-lists-hub-pro.css'],
            'filament.admin.pages.resellers-hub' => ['resellers-hub-pro.css'],
            'filament.admin.pages.inventory-hub' => ['inventory-hub-pro.css'],
            'filament.admin.pages.olt-hub' => ['olt-hub-pro.css'],
            'filament.admin.pages.optical-monitoring-hub' => ['optical-noc.css'],
            'filament.admin.pages.subscriber-traffic' => ['subscriber-live-traffic-pro.css'],
            'filament.admin.pages.network-intelligence-hub' => ['network-intelligence-hub.css'],
            'filament.admin.pages.billing-dashboard' => ['billing-hub-pro.css'],
            'filament.admin.pages.accounting-hub' => ['billing-hub-pro.css'],
            'filament.admin.pages.accounts-hub' => ['billing-hub-pro.css'],
            'filament.admin.pages.collection-desk-report' => ['collection-desk-report-pro.css'],
        ];
    }

    public static function headLinks(): string
    {
        $html = '';

        if (request()->routeIs('filament.admin.resources.subscribers.view')) {
            $html .= SubscriberViewStyles::html();
        }

        if (request()->routeIs('filament.admin.resources.subscribers.*')) {
            $html .= ClientsDirectoryStyles::html();
        }

        if (request()->routeIs('filament.admin.resources.products.*')
            || request()->routeIs('filament.admin.resources.inventory-sales.*')) {
            $html .= self::linkTag('inventory-hub-pro.css');
        }

        foreach (self::stylesheetMap() as $pattern => $files) {
            if (! request()->routeIs($pattern)) {
                continue;
            }

            foreach ($files as $file) {
                $html .= self::linkTag($file);
            }
        }

        return $html;
    }

    private static function linkTag(string $file): string
    {
        $path = public_path('css/'.$file);
        if (! is_file($path)) {
            return '';
        }

        $v = (int) (@filemtime($path) ?: 1);

        return '<link rel="stylesheet" href="'
            .e(asset('css/'.$file).'?v='.$v)
            .'" data-isp-route-asset="'
            .e($file)
            .'">';
    }
}
