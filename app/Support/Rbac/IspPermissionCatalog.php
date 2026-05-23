<?php

namespace App\Support\Rbac;

use App\Models\Permission;

/**
 * Canonical ISP permission keys grouped for RBAC UI and seeders.
 */
final class IspPermissionCatalog
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'customer' => 'A. Customer',
        'billing' => 'B. Billing',
        'payment' => 'C. Payment',
        'mikrotik' => 'D. MikroTik',
        'onu' => 'E. ONU / GPON',
        'ticket' => 'F. Tickets',
        'report' => 'G. Reports',
        'security' => 'H. Security',
        'system' => 'I. System',
        'network' => 'Network / NOC',
        'staff' => 'Staff & branches',
        'inventory' => 'Inventory',
        'accounting' => 'Accounting',
        'reseller' => 'Reseller / franchise',
        'field' => 'Field & technician',
    ];

    /**
     * @return array<string, array<string, string>> category => [permission => label]
     */
    public static function grouped(): array
    {
        return [
            'customer' => [
                'customers.view' => 'View customers',
                'customers.create' => 'Create customer',
                'customers.update' => 'Edit customer',
                'customers.delete' => 'Delete customer',
                'customers.suspend' => 'Suspend customer',
                'customers.reconnect' => 'Reconnect customer',
                'customers.merge' => 'Merge customers',
                'customers.export' => 'Export customers',
                'customers.import' => 'Import customers',
            ],
            'billing' => [
                'billing.view' => 'View billing',
                'billing.manage' => 'Manage billing (full)',
                'invoices.generate' => 'Generate invoice',
                'invoices.edit' => 'Edit invoice',
                'invoices.delete' => 'Delete invoice',
                'billing.discount' => 'Apply discount',
                'billing.adjust' => 'Adjust balance',
                'billing.refund' => 'Approve refund',
                'billing.analytics' => 'Billing analytics',
                'billing.tax_reports' => 'Tax reports',
            ],
            'payment' => [
                'payments.view' => 'View transactions',
                'payments.add' => 'Add payment',
                'payments.approve' => 'Approve payment',
                'payments.refund' => 'Refund payment',
                'payments.export' => 'Export payments',
                'payments.reconcile' => 'Payment reconciliation',
                'payments.wallet' => 'Wallet recharge',
                'payments.daily_close' => 'Daily closing',
                'collections.view' => 'View collector collections & dues',
                'collections.settle' => 'Submit cash settlement to admin',
                'collections.approve' => 'Approve collector settlements',
                'collections.manage' => 'Manage all collector balances',
            ],
            'mikrotik' => [
                'mikrotik.view' => 'View MikroTik',
                'mikrotik.manage' => 'Manage MikroTik (full)',
                'mikrotik.routers.create' => 'Add router',
                'mikrotik.routers.delete' => 'Delete router',
                'mikrotik.reboot' => 'Reboot router',
                'mikrotik.pppoe' => 'Manage PPPoE',
                'mikrotik.queues' => 'Manage queues',
                'mikrotik.traffic' => 'View traffic',
                'mikrotik.hotspot' => 'Hotspot control',
                'mikrotik.vlan' => 'VLAN configuration',
                'mikrotik.firewall' => 'Firewall monitoring',
            ],
            'onu' => [
                'olts.view' => 'View OLT / GPON',
                'olts.manage' => 'Manage OLT / GPON',
                'devices.view' => 'View devices',
                'devices.manage' => 'Manage devices',
                'ports.view' => 'View ports',
                'ports.manage' => 'Manage ports',
                'onu.add' => 'Add ONU',
                'onu.assign' => 'Assign ONU',
                'onu.replace' => 'Replace ONU',
                'onu.signal' => 'Monitor signal',
                'onu.reboot' => 'Reboot ONU',
                'onu.provision' => 'Provision ONU',
                'onu.approve' => 'Approve ONU',
                'onu.vlan' => 'VLAN management',
                'onu.topology' => 'GPON topology',
                'onu.diagnostics' => 'Fiber diagnostics',
            ],
            'ticket' => [
                'support.view' => 'View tickets',
                'support.manage' => 'Manage tickets',
                'support.assign' => 'Assign tickets',
                'tickets.create' => 'Create ticket',
                'tickets.close' => 'Close ticket',
                'tickets.escalate' => 'Escalate ticket',
                'tickets.reopen' => 'Reopen ticket',
                'support.chat' => 'Live chat',
                'support.diagnostics' => 'Basic diagnostics',
                'knowledge.manage' => 'Knowledge base',
                'outages.manage' => 'Outage management',
            ],
            'report' => [
                'reports.view' => 'View reports',
                'reports.revenue' => 'View revenue',
                'reports.export' => 'Export reports',
                'reports.analytics' => 'View analytics',
                'reports.branch' => 'View branch data',
                'reports.noc' => 'View NOC reports',
            ],
            'security' => [
                'security.manage' => 'Security settings',
                'security.roles' => 'Manage roles',
                'audit.view' => 'View audit logs',
                'security.force_logout' => 'Force logout users',
                'security.api_keys' => 'Manage API keys',
                'security.monitoring' => 'Security monitoring',
                'security.impersonate' => 'User impersonation',
            ],
            'system' => [
                'system.settings' => 'Manage settings',
                'system.tenants' => 'Tenant management',
                'system.servers' => 'Server management',
                'system.license' => 'License management',
                'system.themes' => 'Manage themes',
                'system.notifications' => 'Manage notifications',
                'system.automations' => 'Manage automations',
                'system.backups' => 'Manage backups',
                'integrations.view' => 'View integrations',
                'integrations.manage' => 'Manage integrations',
            ],
            'network' => [
                'network.monitor' => 'Router monitoring',
                'network.alerts' => 'Realtime alerts',
                'network.maps' => 'Network maps',
                'network.traffic' => 'Live traffic',
            ],
            'staff' => [
                'staff.view' => 'View staff',
                'staff.manage' => 'Manage staff',
                'staff.kpi' => 'KPI dashboard',
                'branches.view' => 'View branches',
                'branches.manage' => 'Manage branches',
            ],
            'inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.manage' => 'Manage inventory',
                'inventory.warehouse' => 'Warehouse management',
                'inventory.assets' => 'Asset assignment',
                'inventory.purchase' => 'Purchase logs',
            ],
            'accounting' => [
                'accounting.view' => 'View accounting',
                'accounting.manage' => 'Manage accounting',
                'accounting.ledger' => 'Ledger',
                'accounting.expenses' => 'Expenses',
                'accounting.payroll' => 'Payroll',
                'accounting.bank' => 'Bank reconciliation',
                'accounting.vat' => 'VAT reports',
                'payroll.view' => 'View payroll',
                'payroll.manage' => 'Manage payroll',
            ],
            'reseller' => [
                'resellers.view' => 'View resellers / partners',
                'resellers.manage' => 'Manage resellers (full)',
                'resellers.create' => 'Create reseller',
                'resellers.commissions' => 'Commission ledger & payout',
                'resellers.settlements' => 'Settlement approval',
                'resellers.wallet' => 'Wallet top-up & transfers',
                'franchise.areas' => 'Territory / area management',
                'franchise.reports' => 'Franchise reports',
                'franchise.revenue' => 'Revenue & hierarchy tracking',
            ],
            'field' => [
                'field_visits.manage' => 'Field visits',
                'technician.tickets' => 'Assigned tickets',
                'technician.install' => 'Installation workflow',
                'technician.gps' => 'GPS check-in',
                'technician.photos' => 'Installation photos',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        $keys = [];
        foreach (self::grouped() as $permissions) {
            foreach (array_keys($permissions) as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /** @return array<string, string> */
    public static function labeledOptions(): array
    {
        $options = [];
        foreach (self::grouped() as $category => $permissions) {
            $prefix = self::CATEGORIES[$category] ?? $category;
            foreach ($permissions as $key => $label) {
                $record = Permission::query()->where('name', $key)->first();
                $label = $record?->display_name ?? $label;
                $options[$key] = "{$prefix} · {$label}";
            }
        }

        $known = array_keys($options);
        Permission::query()
            ->whereNotIn('name', $known)
            ->orderBy('name')
            ->each(function (Permission $permission) use (&$options): void {
                $prefix = $permission->resolvedCategory() ?? 'Custom';
                $label = $permission->display_name ?? $permission->name;
                $options[$permission->name] = "{$prefix} · {$label}";
            });

        return $options;
    }

    public static function labelFor(string $permission): string
    {
        $record = Permission::query()->where('name', $permission)->first();
        if ($record?->display_name) {
            return $record->display_name;
        }

        foreach (self::grouped() as $permissions) {
            if (isset($permissions[$permission])) {
                return $permissions[$permission];
            }
        }

        return str_replace(['.', '_'], ' ', $permission);
    }

    public static function categoryFor(string $permission): ?string
    {
        $record = Permission::query()->where('name', $permission)->first();
        if ($record?->category) {
            return self::CATEGORIES[$record->category] ?? $record->category;
        }

        foreach (self::grouped() as $category => $permissions) {
            if (isset($permissions[$permission])) {
                return self::CATEGORIES[$category] ?? $category;
            }
        }

        return null;
    }

    public static function categoryKeyFor(string $permission): ?string
    {
        $record = Permission::query()->where('name', $permission)->first();
        if ($record?->category) {
            return $record->category;
        }

        foreach (self::grouped() as $category => $permissions) {
            if (isset($permissions[$permission])) {
                return $category;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function categoryLabels(): array
    {
        return self::CATEGORIES;
    }
}
