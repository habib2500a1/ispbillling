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
            'filament.admin.pages.olt-hub' => ['olt-pro.css'],
            'filament.admin.pages.optical-monitoring-hub' => ['olt-pro.css'],
            'filament.admin.pages.olt-vpn' => ['olt-pro.css'],
            'filament.admin.pages.olt-mac-table' => ['olt-pro.css'],
            'filament.admin.pages.optical-laser-settings' => ['olt-pro.css'],
            'filament.admin.pages.gpon-dashboard' => ['olt-pro.css'],
            'filament.admin.pages.subscriber-traffic' => ['subscriber-live-traffic-pro.css'],
            'filament.admin.pages.network-intelligence-hub' => ['network-pro.css'],
            'filament.admin.pages.mikrotik-dashboard' => ['network-pro.css'],
            'filament.admin.pages.online-clients' => ['network-pro.css'],
            'filament.admin.pages.bandwidth-monitor' => ['network-pro.css'],
            'filament.admin.pages.import-from-mikrotik' => ['network-pro.css'],
            'filament.admin.pages.network-settings' => ['network-pro.css'],
            'filament.admin.pages.billing-dashboard' => ['billing-hub-pro.css', 'billing-pro.css'],
            'filament.admin.pages.billing-overview' => ['billing-hub-pro.css', 'billing-pro.css'],
            'filament.admin.pages.billing-notices' => ['billing-pro.css'],
            'filament.admin.pages.accounting-hub' => ['finance-hub.css'],
            'filament.admin.pages.hr-payroll-hub' => ['workforce-hub.css'],
            'filament.admin.pages.accounts-hub' => ['billing-hub-pro.css'],
            'filament.admin.pages.collection-desk-report' => ['collection-desk-report-pro.css'],
            'filament.admin.pages.reports-hub' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.analytics-reports' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.print-reports' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.churn-zone-reports' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.ai-analytics-dashboard' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.payments-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.due-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.due-report-pro' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.area-wise-clients-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.package-wise-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.export-clients-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.billing-reports' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.gateway-reconciliation-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.btrc-report' => ['reports-intelligence-pro.css'],
            'filament.admin.pages.isp-os' => ['isp-os-pro.css'],
            'filament.admin.pages.fault-center' => ['isp-os-pro.css'],
            'filament.admin.pages.field-technicians' => ['isp-os-pro.css'],
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

        if (request()->routeIs([
            'filament.admin.pages.billing-overview',
            'filament.admin.pages.billing-notices',
            'filament.admin.resources.invoices.*',
        ])) {
            $html .= BillingStyles::html();
        }

        if (request()->routeIs([
            'filament.admin.pages.network-intelligence-hub',
            'filament.admin.pages.mikrotik-dashboard',
            'filament.admin.pages.online-clients',
            'filament.admin.pages.bandwidth-monitor',
            'filament.admin.pages.import-from-mikrotik',
            'filament.admin.pages.network-settings',
            'filament.admin.resources.mikrotik-servers.*',
        ])) {
            $html .= NetworkStyles::html();
        }

        if (request()->routeIs([
            'filament.admin.pages.olt-hub',
            'filament.admin.pages.optical-monitoring-hub',
            'filament.admin.pages.olt-vpn',
            'filament.admin.pages.olt-mac-table',
            'filament.admin.pages.optical-laser-settings',
            'filament.admin.pages.gpon-dashboard',
            'filament.admin.resources.olts.*',
        ])) {
            $html .= OltStyles::html();
        }

        if (request()->routeIs([
            'filament.admin.pages.isp-os',
            'filament.admin.pages.fault-center',
            'filament.admin.pages.field-technicians',
        ])) {
            $html .= IspOsStyles::html();
        }

        if (request()->routeIs([
            'filament.admin.pages.inventory-hub',
            'filament.admin.resources.products.*',
            'filament.admin.resources.inventory-sales.*',
            'filament.admin.resources.warehouses.*',
            'filament.admin.resources.vendors.*',
            'filament.admin.resources.purchase-orders.*',
            'filament.admin.resources.stock-movements.*',
            'filament.admin.resources.fixed-assets.*',
            'filament.admin.resources.store-device-loans.*',
            'filament.admin.resources.devices.*',
            'filament.admin.resources.pop-boxes.*',
            'filament.admin.pages.inventory-*',
        ])) {
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

        $mtime = (int) (@filemtime($path) ?: 1);
        $salt = (int) config('isp.assets.version_salt', 0);
        $v = $mtime + ($salt * 1_000_000);

        return '<link rel="stylesheet" href="'
            .e(asset('css/'.$file).'?v='.$v)
            .'" data-isp-route-asset="'
            .e($file)
            .'">';
    }

    /**
     * Livewire SPA: page CSS in &lt;head&gt; only renders on first load — reinject after navigate.
     */
    public static function spaStyleLoaderScript(): string
    {
        $rules = [];

        $directoryAssets = self::stylesheetAssetList(
            ClientsDirectoryStyles::modules(),
            ClientsDirectoryStyles::BUNDLE_FILE,
            'clients-directory',
        );
        if ($directoryAssets !== []) {
            $rules[] = [
                'match' => 'subscribers-directory',
                'assets' => $directoryAssets,
            ];
        }

        $viewAssets = self::stylesheetAssetList(
            SubscriberViewStyles::modules(),
            SubscriberViewStyles::BUNDLE_FILE,
            'subscriber-view',
        );
        if ($viewAssets !== []) {
            $rules[] = [
                'match' => 'subscribers-view',
                'assets' => $viewAssets,
            ];
        }

        $billingAssets = self::stylesheetAssetList(
            BillingStyles::modules(),
            BillingStyles::BUNDLE_FILE,
            'billing',
        );
        if ($billingAssets !== []) {
            $rules[] = [
                'match' => 'billing-module',
                'assets' => $billingAssets,
            ];
        }

        $networkAssets = self::stylesheetAssetList(
            NetworkStyles::modules(),
            NetworkStyles::BUNDLE_FILE,
            'network',
        );
        if ($networkAssets !== []) {
            $rules[] = [
                'match' => 'network-module',
                'assets' => $networkAssets,
            ];
        }

        $oltAssets = self::stylesheetAssetList(
            OltStyles::modules(),
            OltStyles::BUNDLE_FILE,
            'olt',
        );
        if ($oltAssets !== []) {
            $rules[] = [
                'match' => 'olt-module',
                'assets' => $oltAssets,
            ];
        }

        $ispOsAssets = self::stylesheetAssetList(
            IspOsStyles::modules(),
            IspOsStyles::BUNDLE_FILE,
            'isp-os',
        );
        if ($ispOsAssets !== []) {
            $rules[] = [
                'match' => 'isp-os-module',
                'assets' => $ispOsAssets,
            ];
        }

        foreach (self::stylesheetMap() as $pattern => $files) {
            $pathPrefix = self::pathPrefixForRoutePattern($pattern);
            if ($pathPrefix === null) {
                continue;
            }

            $assets = [];
            foreach ($files as $file) {
                $path = public_path('css/'.$file);
                if (! is_file($path)) {
                    continue;
                }

                $mtime = (int) (@filemtime($path) ?: 1);
                $salt = (int) config('isp.assets.version_salt', 0);
                $assets[] = [
                    'id' => 'isp-route-'.str_replace('.', '-', $file),
                    'href' => asset('css/'.$file).'?v='.($mtime + ($salt * 1_000_000)),
                ];
            }

            if ($assets !== []) {
                $rules[] = [
                    'match' => 'path-prefix',
                    'prefix' => $pathPrefix,
                    'assets' => $assets,
                ];
            }
        }

        if ($rules === []) {
            return '';
        }

        $json = json_encode($rules, JSON_UNESCAPED_SLASHES);
        $presets = json_encode(self::subscriberDirectoryPresets(), JSON_UNESCAPED_SLASHES);

        return <<<JS
<script data-cfasync="false">
(function () {
    var rules = {$json};
    var directoryPresets = {$presets};

    function injectAssets(assets) {
        assets.forEach(function (asset) {
            var existing = document.getElementById(asset.id);
            if (existing && existing.getAttribute('href') === asset.href) {
                if (existing.parentNode !== document.head) {
                    document.head.appendChild(existing);
                }
                return;
            }
            if (existing) {
                existing.remove();
            }
            var link = document.createElement('link');
            link.id = asset.id;
            link.rel = 'stylesheet';
            link.href = asset.href;
            link.setAttribute('data-isp-spa-style', '1');
            document.head.appendChild(link);
        });
    }

    function matchesRule(rule) {
        var path = window.location.pathname;

        if (rule.match === 'subscribers-directory') {
            if (! path.startsWith('/admin/subscribers')) {
                return false;
            }
            var segment = path.replace(/^\\/admin\\/subscribers\\/?/, '').split('/')[0] || '';
            return segment === '' || directoryPresets.indexOf(segment) !== -1;
        }

        if (rule.match === 'subscribers-view') {
            if (! path.startsWith('/admin/subscribers/')) {
                return false;
            }
            var viewSegment = path.replace(/^\\/admin\\/subscribers\\/?/, '').split('/')[0] || '';
            return viewSegment !== '' && directoryPresets.indexOf(viewSegment) === -1;
        }

        if (rule.match === 'billing-module') {
            return /\\/admin\\/(billing-overview|billing-notices|invoices|bill-collection|payments-report|collection-desk-report)/.test(path);
        }

        if (rule.match === 'network-module') {
            return /\\/admin\\/(network-intelligence-hub|mikrotik-dashboard|network-settings|mikrotik-servers|online-clients|bandwidth-monitor|import-from-mikrotik)/.test(path);
        }

        if (rule.match === 'olt-module') {
            return /\\/admin\\/(olt-hub|optical-noc|olt-vpn|olt-mac-table|optical-laser-settings|gpon-dashboard|olts)/.test(path);
        }

        if (rule.match === 'isp-os-module') {
            return /\\/admin\\/(isp-os|fault-center|field-technicians)/.test(path);
        }

        if (rule.match === 'path-prefix') {
            return path === rule.prefix || path.startsWith(rule.prefix + '/');
        }

        return false;
    }

    function applySpaRouteStyles() {
        rules.forEach(function (rule) {
            if (matchesRule(rule)) {
                injectAssets(rule.assets);
            }
        });
    }

    applySpaRouteStyles();
    document.addEventListener('livewire:navigated', applySpaRouteStyles);
})();
</script>
JS;
    }

    /**
     * @return list<string>
     */
    private static function subscriberDirectoryPresets(): array
    {
        return [
            'active',
            'create',
            'due',
            'expire-3',
            'expire-7',
            'expired',
            'free',
            'left',
            'pending',
            'suspended',
            'today',
            'vip',
        ];
    }

    /**
     * @param  list<string>  $modules
     * @return list<array{id: string, href: string}>
     */
    private static function stylesheetAssetList(array $modules, ?string $bundleFile, string $idPrefix): array
    {
        if ($bundleFile !== null && StylesheetModules::shouldBundle() && is_file(public_path('css/'.$bundleFile))) {
            $v = StylesheetModules::version($modules, $bundleFile);

            return [[
                'id' => $idPrefix.'-bundle',
                'href' => asset('css/'.$bundleFile).'?v='.$v,
            ]];
        }

        $v = StylesheetModules::version($modules);
        $assets = [];

        foreach ($modules as $file) {
            if (! is_file(public_path('css/'.$file))) {
                continue;
            }

            $slug = basename($file, '.css');
            $assets[] = [
                'id' => $idPrefix.'-'.$slug,
                'href' => asset('css/'.$file).'?v='.$v,
            ];
        }

        return $assets;
    }

    private static function pathPrefixForRoutePattern(string $pattern): ?string
    {
        $map = [
            'filament.admin.pages.clients-hub' => '/admin/clients-hub',
            'filament.admin.pages.subscriber-lists-hub' => '/admin/subscriber-lists-hub',
            'filament.admin.pages.resellers-hub' => '/admin/resellers-hub',
            'filament.admin.pages.inventory-hub' => '/admin/inventory-hub',
            'filament.admin.pages.olt-hub' => '/admin/olt-hub',
            'filament.admin.pages.optical-monitoring-hub' => '/admin/optical-noc',
            'filament.admin.pages.subscriber-traffic' => '/admin/subscriber-traffic',
            'filament.admin.pages.network-intelligence-hub' => '/admin/network-intelligence-hub',
            'filament.admin.pages.mikrotik-dashboard' => '/admin/mikrotik-dashboard',
            'filament.admin.pages.online-clients' => '/admin/online-clients',
            'filament.admin.pages.bandwidth-monitor' => '/admin/bandwidth-monitor',
            'filament.admin.pages.import-from-mikrotik' => '/admin/import-from-mikrotik',
            'filament.admin.pages.network-settings' => '/admin/network-settings',
            'filament.admin.pages.billing-dashboard' => '/admin/billing-dashboard',
            'filament.admin.pages.billing-overview' => '/admin/billing-overview',
            'filament.admin.pages.billing-notices' => '/admin/billing-notices',
            'filament.admin.pages.accounting-hub' => '/admin/accounting-hub',
            'filament.admin.pages.accounts-hub' => '/admin/accounts-hub',
            'filament.admin.pages.collection-desk-report' => '/admin/collection-desk-report',
        ];

        return $map[$pattern] ?? null;
    }
}
