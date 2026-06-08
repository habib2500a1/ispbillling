<?php

namespace App\Support\Rbac;

/**
 * Staff-facing modules mapped to Spatie permission keys (main admin can toggle per role).
 */
final class IspModuleCatalog
{
    /**
     * @return array<string, array{label: string, hint: string, gate: string, permissions: list<string>}>
     */
    public static function modules(): array
    {
        $grouped = IspPermissionCatalog::grouped();

        return [
            'billing' => [
                'label' => 'Billing & invoices',
                'hint' => 'Generate bill, edit invoice, discount',
                'gate' => 'billing.view',
                'permissions' => array_keys($grouped['billing']),
            ],
            'payments' => [
                'label' => 'Payments & collection',
                'hint' => 'Cash, wallet, collector settlement',
                'gate' => 'payments.view',
                'permissions' => array_keys($grouped['payment']),
            ],
            'inventory' => [
                'label' => 'Inventory & purchase',
                'hint' => 'Stock, warehouse, buy / PO',
                'gate' => 'inventory.view',
                'permissions' => array_keys($grouped['inventory']),
            ],
            'olt' => [
                'label' => 'OLT / GPON',
                'hint' => 'OLT, ONU, signal, provision',
                'gate' => 'olts.view',
                'permissions' => array_keys($grouped['onu']),
            ],
            'map' => [
                'label' => 'Network map',
                'hint' => 'Fiber plant map, GIS topology',
                'gate' => 'network.maps',
                'permissions' => array_values(array_unique(array_merge(
                    ['network.maps'],
                    array_intersect(
                        ['onu.topology', 'network.monitor'],
                        array_keys($grouped['onu'] + $grouped['network']),
                    ),
                ))),
            ],
            'mikrotik' => [
                'label' => 'MikroTik / routers',
                'hint' => 'PPPoE, queues, traffic',
                'gate' => 'mikrotik.view',
                'permissions' => array_keys($grouped['mikrotik']),
            ],
            'network' => [
                'label' => 'NOC monitoring',
                'hint' => 'Alerts, live traffic',
                'gate' => 'network.monitor',
                'permissions' => array_keys($grouped['network']),
            ],
            'support' => [
                'label' => 'Support & tickets',
                'hint' => 'Tickets, chat, outages',
                'gate' => 'support.view',
                'permissions' => array_keys($grouped['ticket']),
            ],
            'reports' => [
                'label' => 'Reports',
                'hint' => 'Revenue, export, analytics',
                'gate' => 'reports.view',
                'permissions' => array_keys($grouped['report']),
            ],
            'accounting' => [
                'label' => 'Accounting & payroll',
                'hint' => 'Ledger, expenses, VAT',
                'gate' => 'accounting.view',
                'permissions' => array_values(array_unique(array_merge(
                    array_keys($grouped['accounting']),
                ))),
            ],
            'resellers' => [
                'label' => 'Resellers / franchise',
                'hint' => 'Partner, commission, wallet',
                'gate' => 'resellers.view',
                'permissions' => array_keys($grouped['reseller']),
            ],
            'sms' => [
                'label' => 'SMS & notifications',
                'hint' => 'Templates, SMS gateway',
                'gate' => 'system.notifications',
                'permissions' => ['system.notifications', 'integrations.view'],
            ],
            'automation' => [
                'label' => 'Automatic processes',
                'hint' => 'Billing cron, sync jobs',
                'gate' => 'system.automations',
                'permissions' => ['system.automations'],
            ],
            'staff' => [
                'label' => 'Staff & branches',
                'hint' => 'Users, roles, branches',
                'gate' => 'staff.view',
                'permissions' => array_keys($grouped['staff']),
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::modules()[$key] ?? null;
    }

    /** @return list<string> */
    public static function permissionKeys(string $moduleKey): array
    {
        $module = self::get($moduleKey);

        return $module['permissions'] ?? [];
    }

    public static function gatePermission(string $moduleKey): ?string
    {
        $module = self::get($moduleKey);

        return $module['gate'] ?? null;
    }
}
