<?php

namespace App\Support\Rbac;

/**
 * Built-in ISP role templates (16 main roles).
 */
final class IspRoleTemplates
{
    /**
     * @return array<string, array{label: string, description: string, permissions: list<string>|'*', legacy?: list<string>}>
     */
    public static function all(): array
    {
        return [
            'super-admin' => [
                'label' => 'Super Admin',
                'description' => 'Full system access — tenants, security, license, impersonation, all modules.',
                'permissions' => '*',
            ],
            'isp-owner' => [
                'label' => 'ISP Owner / Director',
                'description' => 'Business control — revenue, KPI, financial & network overview.',
                'permissions' => self::keys(
                    'customers.view', 'billing.view', 'billing.analytics', 'payments.view',
                    'reports.view', 'reports.revenue', 'reports.analytics', 'reports.export',
                    'staff.view', 'staff.kpi', 'branches.view', 'branches.manage',
                    'network.monitor', 'network.maps', 'outages.manage', 'audit.view',
                    'accounting.view', 'resellers.view', 'franchise.reports', 'franchise.revenue',
                ),
            ],
            'admin' => [
                'label' => 'Admin',
                'description' => 'Operational full control — customers, billing, network, tickets, reports.',
                'permissions' => self::allExcept(
                    'security.impersonate', 'system.tenants', 'system.license', 'system.servers',
                ),
                'legacy' => ['isp-admin'],
            ],
            'branch-manager' => [
                'label' => 'Branch Manager',
                'description' => 'Branch-wise customers, staff, collections, tickets.',
                'permissions' => self::keys(
                    'customers.view', 'customers.create', 'customers.update', 'customers.suspend',
                    'billing.view', 'payments.view', 'payments.add', 'payments.daily_close',
                    'reports.view', 'reports.branch', 'reports.export',
                    'staff.view', 'branches.view', 'support.view', 'support.assign',
                    'inventory.view', 'field_visits.manage', 'technician.tickets',
                ),
                'legacy' => ['isp-manager'],
            ],
            'noc-engineer' => [
                'label' => 'NOC Engineer',
                'description' => 'Network monitoring — routers, OLT, traffic, alerts, outages.',
                'permissions' => self::keys(
                    'network.monitor', 'network.alerts', 'network.maps', 'network.traffic',
                    'mikrotik.view', 'mikrotik.traffic', 'mikrotik.reboot',
                    'olts.view', 'devices.view', 'ports.view', 'onu.signal',
                    'outages.manage', 'reports.view', 'reports.noc', 'support.view',
                ),
            ],
            'gpon-engineer' => [
                'label' => 'GPON Engineer',
                'description' => 'Fiber / OLT — provision, signal, VLAN, topology.',
                'permissions' => self::keys(
                    'olts.view', 'olts.manage', 'devices.view', 'devices.manage',
                    'ports.view', 'ports.manage',
                    'onu.add', 'onu.assign', 'onu.replace', 'onu.signal', 'onu.reboot',
                    'onu.provision', 'onu.approve', 'onu.vlan', 'onu.topology', 'onu.diagnostics',
                    'network.monitor', 'outages.manage',
                ),
            ],
            'mikrotik-engineer' => [
                'label' => 'MikroTik Engineer',
                'description' => 'Router / PPPoE — queues, hotspot, shaping, firewall.',
                'permissions' => self::keys(
                    'mikrotik.view', 'mikrotik.manage', 'mikrotik.routers.create', 'mikrotik.reboot',
                    'mikrotik.pppoe', 'mikrotik.queues', 'mikrotik.traffic', 'mikrotik.hotspot',
                    'mikrotik.vlan', 'mikrotik.firewall', 'network.monitor', 'network.traffic',
                    'customers.view',
                ),
                'legacy' => ['isp-engineer'],
            ],
            'billing-manager' => [
                'label' => 'Billing Manager',
                'description' => 'Invoices, dues, discounts, reconciliation, tax.',
                'permissions' => self::keys(
                    'customers.view', 'billing.view', 'billing.manage',
                    'invoices.generate', 'invoices.edit', 'invoices.delete',
                    'billing.discount', 'billing.adjust', 'billing.refund',
                    'billing.analytics', 'billing.tax_reports',
                    'payments.view', 'payments.reconcile', 'reports.view', 'reports.revenue',
                ),
            ],
            'cashier' => [
                'label' => 'Cashier / Collector',
                'description' => 'Collect payments, receipts, wallet, daily closing.',
                'permissions' => self::keys(
                    'customers.view', 'billing.view', 'payments.view', 'payments.add',
                    'payments.wallet', 'payments.daily_close', 'collections.view', 'collections.settle',
                    'reports.view', 'reports.branch',
                ),
            ],
            'support-agent' => [
                'label' => 'Support Agent',
                'description' => 'Tickets, live chat, customer profile, escalation.',
                'permissions' => self::keys(
                    'customers.view', 'support.view', 'support.manage',
                    'tickets.create', 'tickets.close', 'tickets.escalate',
                    'support.chat', 'support.diagnostics', 'knowledge.manage',
                ),
                'legacy' => ['isp-support'],
            ],
            'technician' => [
                'label' => 'Technician',
                'description' => 'Field work — assigned tickets, install, GPS, photos.',
                'permissions' => self::keys(
                    'customers.view', 'technician.tickets', 'technician.install',
                    'technician.gps', 'technician.photos', 'field_visits.manage',
                    'onu.assign', 'onu.signal', 'devices.view',
                ),
            ],
            'inventory-manager' => [
                'label' => 'Inventory Manager',
                'description' => 'ONU/router stock, warehouse, assets, purchases.',
                'permissions' => self::keys(
                    'inventory.view', 'inventory.manage', 'inventory.warehouse',
                    'inventory.assets', 'inventory.purchase', 'devices.view', 'reports.view',
                ),
            ],
            'accountant' => [
                'label' => 'Accountant',
                'description' => 'Ledger, expenses, payroll, bank, VAT.',
                'permissions' => self::keys(
                    'accounting.view', 'accounting.manage', 'accounting.ledger',
                    'accounting.expenses', 'accounting.payroll', 'accounting.bank', 'accounting.vat',
                    'payroll.view', 'payroll.manage', 'billing.view', 'payments.view',
                    'reports.view', 'reports.revenue', 'reports.export',
                ),
            ],
            'reseller' => [
                'label' => 'Reseller',
                'description' => 'Own customers, billing, collection, limited tickets.',
                'permissions' => self::keys(
                    'customers.view', 'customers.create', 'customers.update',
                    'billing.view', 'payments.view', 'payments.add',
                    'support.view', 'support.manage', 'resellers.view', 'reports.view', 'reports.branch',
                ),
            ],
            'franchise-admin' => [
                'label' => 'Franchise Admin',
                'description' => 'Multi-area franchise — areas, revenue, technicians.',
                'permissions' => self::keys(
                    'franchise.areas', 'franchise.reports', 'franchise.revenue',
                    'customers.view', 'branches.view', 'staff.view', 'support.view', 'support.assign',
                    'reports.view', 'reports.branch', 'reports.analytics', 'field_visits.manage',
                ),
            ],
            'customer' => [
                'label' => 'Customer (portal)',
                'description' => 'Self-service portal only — not for staff admin panel.',
                'permissions' => [],
            ],
        ];
    }

    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** @return array<string, string> slug => label */
    public static function options(): array
    {
        $opts = [];
        foreach (self::all() as $slug => $meta) {
            $opts[$slug] = $meta['label'];
        }

        return $opts;
    }

    /** @return list<string> */
    public static function permissionKeysFor(string $slug): array
    {
        $meta = self::get($slug);
        if ($meta === null) {
            return [];
        }

        if ($meta['permissions'] === '*') {
            return IspPermissionCatalog::all();
        }

        return $meta['permissions'];
    }

    /** @param list<string> $keys */
    private static function keys(string ...$keys): array
    {
        return $keys;
    }

    /** @param list<string> $except */
    private static function allExcept(string ...$except): array
    {
        return array_values(array_diff(IspPermissionCatalog::all(), $except));
    }
}
