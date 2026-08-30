<?php

namespace App\Support;

/**
 * Complete anetbd / ispbilling feature catalog for Code Pagol.
 * Every module is reachable — either an existing route or /isp/{slug}.
 */
final class FeatureModuleRegistry
{
    /**
     * @return list<array{
     *   slug: string,
     *   group: string,
     *   section: string,
     *   label: string,
     *   description: string,
     *   icon: string,
     *   accent: string,
     *   route?: string,
     *   route_params?: array<string, mixed>,
     *   permission?: list<string>,
     * }>
     */
    public static function all(): array
    {
        return array_merge(
            self::ispOs(),
            self::subscribers(),
            self::billing(),
            self::payments(),
            self::olt(),
            self::network(),
            self::support(),
            self::hr(),
            self::inventory(),
            self::finance(),
            self::resellers(),
            self::reports(),
            self::system(),
        );
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $module) {
            if ($module['slug'] === $slug) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(self::all(), fn (array $m): bool => $m['group'] === $group));
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_column(self::all(), 'group')));
    }

    public static function groupSlug(string $group): string
    {
        return \Illuminate\Support\Str::slug($group);
    }

    public static function groupFromSlug(string $slug): ?string
    {
        foreach (self::groups() as $group) {
            if (self::groupSlug($group) === $slug) {
                return $group;
            }
        }

        return null;
    }

    public static function url(array $module): string
    {
        if (! empty($module['route'])) {
            return route($module['route'], $module['route_params'] ?? []);
        }

        return route('isp.module', ['module' => $module['slug']]);
    }

    /** @return list<array<string, mixed>> */
    private static function ispOs(): array
    {
        return [
            ['slug' => 'isp-os-center', 'group' => 'ISP OS', 'section' => 'Command', 'label' => 'ISP OS Center', 'description' => 'Unified operations — billing, network, GIS, faults', 'icon' => 'bi-command', 'accent' => 'indigo', 'route' => 'isp-os'],
            ['slug' => 'ai-copilot', 'group' => 'ISP OS', 'section' => 'Command', 'label' => 'AI Operations Copilot', 'description' => 'Billing, NOC, tickets intelligence', 'icon' => 'bi-stars', 'accent' => 'indigo'],
            ['slug' => 'fault-management', 'group' => 'ISP OS', 'section' => 'NOC', 'label' => 'Fault management', 'description' => 'Active faults, RCA, severity', 'icon' => 'bi-exclamation-triangle', 'accent' => 'danger'],
            ['slug' => 'field-technicians', 'group' => 'ISP OS', 'section' => 'Field', 'label' => 'Field technicians', 'description' => 'Visits, tasks, mobile tools', 'icon' => 'bi-tools', 'accent' => 'warning'],
            ['slug' => 'noc-wall', 'group' => 'ISP OS', 'section' => 'NOC', 'label' => 'NOC wall', 'description' => '24/7 large-screen monitoring', 'icon' => 'bi-tv', 'accent' => 'dark'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function subscribers(): array
    {
        return [
            ['slug' => 'all-subscribers', 'group' => 'Subscribers', 'section' => 'Main', 'label' => 'All subscribers', 'description' => 'Search, edit, PPPoE & billing', 'icon' => 'bi-people', 'accent' => 'teal', 'route' => 'customers.index'],
            ['slug' => 'packages', 'group' => 'Subscribers', 'section' => 'Catalog', 'label' => 'Packages', 'description' => 'Plans, speed & pricing', 'icon' => 'bi-box2', 'accent' => 'teal', 'route' => 'package-list-setup'],
            ['slug' => 'zones-areas', 'group' => 'Subscribers', 'section' => 'Coverage', 'label' => 'Zones & areas', 'description' => 'Area → zone → subzone', 'icon' => 'bi-geo-alt', 'accent' => 'teal', 'route' => 'address-setup'],
            ['slug' => 'new-subscriber', 'group' => 'Subscribers', 'section' => 'Main', 'label' => 'New subscriber', 'description' => 'Create customer account', 'icon' => 'bi-person-plus', 'accent' => 'teal', 'route' => 'new-customer'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function billing(): array
    {
        return [
            ['slug' => 'billing-center', 'group' => 'Billing', 'section' => 'Hub', 'label' => 'Billing center', 'description' => 'Open bills · overdue · collections', 'icon' => 'bi-cash-stack', 'accent' => 'violet', 'route' => 'billing-notices'],
            ['slug' => 'bill-collection-desk', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Bill collection desk', 'description' => 'Search subscriber · collect payment', 'icon' => 'bi-currency-exchange', 'accent' => 'success', 'route' => 'payment-collection'],
            ['slug' => 'payment-report', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Payment & bKash report', 'description' => 'Collection statement · method filters', 'icon' => 'bi-file-earmark-bar-graph', 'accent' => 'success', 'route' => 'collection-report.index'],
            ['slug' => 'bkash-collections', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'bKash collections', 'description' => 'All bKash payments in date range', 'icon' => 'bi-phone', 'accent' => 'pink'],
            ['slug' => 'wallets', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Wallets', 'description' => 'Cashbook · bank · collector cash', 'icon' => 'bi-wallet2', 'accent' => 'indigo', 'route' => 'accounts-hub'],
            ['slug' => 'staff-performance', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Staff performance', 'description' => 'Collection & new lines by staff', 'icon' => 'bi-person-workspace', 'accent' => 'info'],
            ['slug' => 'bill-money-trail', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Bill money trail', 'description' => 'Collections · invoice · wallet · expenses', 'icon' => 'bi-arrow-left-right', 'accent' => 'violet'],
            ['slug' => 'staff-expenses', 'group' => 'Billing', 'section' => 'Collections', 'label' => 'Staff expenses', 'description' => 'Submit · approve vendor costs', 'icon' => 'bi-receipt', 'accent' => 'danger', 'route' => 'admin.expenses'],
            ['slug' => 'invoices', 'group' => 'Billing', 'section' => 'Documents', 'label' => 'Invoices', 'description' => 'Generate, print, due dates', 'icon' => 'bi-receipt-cutoff', 'accent' => 'violet', 'route' => 'payment-invoice'],
            ['slug' => 'late-fees', 'group' => 'Billing', 'section' => 'Documents', 'label' => 'Late fees', 'description' => 'Apply and configure late fees', 'icon' => 'bi-clock-history', 'accent' => 'warning'],
            ['slug' => 'coupons', 'group' => 'Billing', 'section' => 'Promotions', 'label' => 'Coupons', 'description' => 'Discount codes', 'icon' => 'bi-ticket-perforated', 'accent' => 'violet'],
            ['slug' => 'offers', 'group' => 'Billing', 'section' => 'Promotions', 'label' => 'Offers & promotions', 'description' => 'Package promos & date ranges', 'icon' => 'bi-gift', 'accent' => 'violet'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function payments(): array
    {
        return [
            ['slug' => 'payment-center', 'group' => 'Payments', 'section' => 'Hub', 'label' => 'Payment center', 'description' => 'bKash · Nagad · SSLCommerz', 'icon' => 'bi-credit-card', 'accent' => 'success'],
            ['slug' => 'collections-log', 'group' => 'Payments', 'section' => 'Records', 'label' => 'Collections', 'description' => 'Receipts & payment log', 'icon' => 'bi-journal-check', 'accent' => 'success', 'route' => 'collection-edit'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function olt(): array
    {
        return [
            ['slug' => 'olt-center', 'group' => 'OLT & Tools', 'section' => 'Hub', 'label' => 'OLT center', 'description' => 'Aveis · BDCOM · optical dBm', 'icon' => 'bi-hdd-network', 'accent' => 'violet', 'route' => 'olt-management'],
            ['slug' => 'olt-list', 'group' => 'OLT & Tools', 'section' => 'Devices', 'label' => 'OLT list', 'description' => 'Add OLT, sync ONUs, edit PON', 'icon' => 'bi-broadcast', 'accent' => 'violet', 'route' => 'olt-management'],
            ['slug' => 'optical-database', 'group' => 'OLT & Tools', 'section' => 'Optical', 'label' => 'Optical database', 'description' => 'Receive power RX dBm', 'icon' => 'bi-lightbulb', 'accent' => 'violet', 'route' => 'onu-management'],
            ['slug' => 'network-topology', 'group' => 'OLT & Tools', 'section' => 'Map', 'label' => 'Topology', 'description' => 'OLT → PON → ONU', 'icon' => 'bi-diagram-3', 'accent' => 'violet'],
            ['slug' => 'fiber-plant-map', 'group' => 'OLT & Tools', 'section' => 'Map', 'label' => 'Fiber plant map', 'description' => 'Splitter · cable · meter', 'icon' => 'bi-map', 'accent' => 'violet'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function network(): array
    {
        return [
            ['slug' => 'network-center', 'group' => 'Network', 'section' => 'Hub', 'label' => 'Network center', 'description' => 'SNMP · NetFlow · routers', 'icon' => 'bi-cpu', 'accent' => 'cyan', 'route' => 'noc-overview'],
            ['slug' => 'mikrotik', 'group' => 'Network', 'section' => 'Core', 'label' => 'MikroTik', 'description' => 'Routers & PPPoE sync', 'icon' => 'bi-router', 'accent' => 'cyan', 'route' => 'mikrotik-sync'],
            ['slug' => 'radius-users', 'group' => 'Network', 'section' => 'Core', 'label' => 'RADIUS users', 'description' => 'radcheck / radusergroup admin', 'icon' => 'bi-database', 'accent' => 'cyan', 'route' => 'mikrotik-radius-setup'],
            ['slug' => 'online-clients', 'group' => 'Network', 'section' => 'Monitor', 'label' => 'Online clients', 'description' => 'Live PPP sessions', 'icon' => 'bi-wifi', 'accent' => 'cyan', 'route' => 'online-clients'],
            ['slug' => 'bandwidth', 'group' => 'Network', 'section' => 'Monitor', 'label' => 'Bandwidth', 'description' => 'Usage & abuse alerts', 'icon' => 'bi-speedometer2', 'accent' => 'cyan', 'route' => 'bandwidth-hub'],
            ['slug' => 'snmp-monitor', 'group' => 'Network', 'section' => 'Monitor', 'label' => 'SNMP monitor', 'description' => 'Poll logs & interface status', 'icon' => 'bi-activity', 'accent' => 'cyan'],
            ['slug' => 'netflow-analysis', 'group' => 'Network', 'section' => 'Monitor', 'label' => 'NetFlow analysis', 'description' => 'Top talkers & protocols', 'icon' => 'bi-shuffle', 'accent' => 'cyan'],
            ['slug' => 'pop-boxes', 'group' => 'Network', 'section' => 'Infrastructure', 'label' => 'POP / boxes', 'description' => 'Sites & capacity', 'icon' => 'bi-building', 'accent' => 'cyan'],
            ['slug' => 'ip-pools', 'group' => 'Network', 'section' => 'IPAM', 'label' => 'IP pools', 'description' => 'Static IP allocation', 'icon' => 'bi-globe2', 'accent' => 'cyan', 'route' => 'mikrotik-ip-setup'],
            ['slug' => 'hotspot-vouchers', 'group' => 'Network', 'section' => 'Hotspot', 'label' => 'Hotspot vouchers', 'description' => 'Prepaid Wi‑Fi cards', 'icon' => 'bi-ticket', 'accent' => 'cyan', 'route' => 'admin.vouchers'],
            ['slug' => 'hotspot-portal', 'group' => 'Network', 'section' => 'Hotspot', 'label' => 'Hotspot portal', 'description' => 'Captive voucher login', 'icon' => 'bi-wifi', 'accent' => 'cyan', 'route' => 'mikrotik-hotspot-manager'],
            ['slug' => 'session-integrity', 'group' => 'Network', 'section' => 'Monitor', 'label' => 'Session integrity', 'description' => 'Multi-router & overdue online scan', 'icon' => 'bi-shield-check', 'accent' => 'cyan'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function support(): array
    {
        return [
            ['slug' => 'support-center', 'group' => 'Support', 'section' => 'Hub', 'label' => 'Support center', 'description' => 'Tickets · SLA · chat', 'icon' => 'bi-life-preserver', 'accent' => 'warning', 'route' => 'admin-tickets'],
            ['slug' => 'call-center', 'group' => 'Support', 'section' => 'Call center', 'label' => 'Call center', 'description' => 'Logs · follow-ups · SIP', 'icon' => 'bi-telephone', 'accent' => 'warning', 'route' => 'call-desk'],
            ['slug' => 'call-reports', 'group' => 'Support', 'section' => 'Call center', 'label' => 'Call reports', 'description' => 'Staff call performance', 'icon' => 'bi-bar-chart', 'accent' => 'warning'],
            ['slug' => 'new-connections', 'group' => 'Support', 'section' => 'CRM', 'label' => 'New connections', 'description' => 'Website signup · convert', 'icon' => 'bi-person-plus', 'accent' => 'warning'],
            ['slug' => 'sales-pipeline', 'group' => 'Support', 'section' => 'CRM', 'label' => 'Connection pipeline', 'description' => 'Kanban by stage', 'icon' => 'bi-kanban', 'accent' => 'warning'],
            ['slug' => 'all-tickets', 'group' => 'Support', 'section' => 'Tickets', 'label' => 'All tickets', 'description' => 'Complaints queue', 'icon' => 'bi-chat-left-text', 'accent' => 'warning', 'route' => 'admin-tickets'],
            ['slug' => 'task-board', 'group' => 'Support', 'section' => 'Tasks', 'label' => 'Task board', 'description' => 'Kanban for staff', 'icon' => 'bi-columns-gap', 'accent' => 'warning'],
            ['slug' => 'outage-broadcast', 'group' => 'Support', 'section' => 'Alerts', 'label' => 'Outage broadcast', 'description' => 'SMS / email alerts', 'icon' => 'bi-megaphone', 'accent' => 'warning', 'route' => 'noc-outage'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function hr(): array
    {
        return [
            ['slug' => 'workforce-ops', 'group' => 'HR & Payroll', 'section' => 'Hub', 'label' => 'Workforce operations', 'description' => 'Employees · attendance · payroll', 'icon' => 'bi-briefcase', 'accent' => 'rose', 'route' => 'hr-hub'],
            ['slug' => 'employees', 'group' => 'HR & Payroll', 'section' => 'Staff', 'label' => 'Employees', 'description' => 'Staff profiles', 'icon' => 'bi-person-badge', 'accent' => 'rose', 'route' => 'admin-users'],
            ['slug' => 'payroll-runs', 'group' => 'HR & Payroll', 'section' => 'Payroll', 'label' => 'Payroll runs', 'description' => 'Salary processing', 'icon' => 'bi-cash-coin', 'accent' => 'rose'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function inventory(): array
    {
        return [
            ['slug' => 'inventory-center', 'group' => 'Inventory', 'section' => 'Hub', 'label' => 'Inventory center', 'description' => 'Stock · POS · warehouses', 'icon' => 'bi-box-seam', 'accent' => 'orange', 'route' => 'inventory-hub'],
            ['slug' => 'warehouses', 'group' => 'Inventory', 'section' => 'Stock', 'label' => 'Warehouses', 'description' => 'Multi-warehouse & transfer', 'icon' => 'bi-building', 'accent' => 'orange'],
            ['slug' => 'products', 'group' => 'Inventory', 'section' => 'Stock', 'label' => 'Products · barcode', 'description' => 'SKU · scan · pricing', 'icon' => 'bi-upc-scan', 'accent' => 'orange', 'route' => 'inventory-hub'],
            ['slug' => 'pos-sale', 'group' => 'Inventory', 'section' => 'POS', 'label' => 'New sale (POS)', 'description' => 'Barcode retail sale', 'icon' => 'bi-qr-code-scan', 'accent' => 'orange'],
            ['slug' => 'retail-sales', 'group' => 'Inventory', 'section' => 'POS', 'label' => 'Retail sales', 'description' => 'POS history', 'icon' => 'bi-receipt', 'accent' => 'orange'],
            ['slug' => 'stock-ledger', 'group' => 'Inventory', 'section' => 'Stock', 'label' => 'Stock ledger', 'description' => 'In / out by warehouse', 'icon' => 'bi-arrow-repeat', 'accent' => 'orange'],
            ['slug' => 'devices-onu', 'group' => 'Inventory', 'section' => 'Stock', 'label' => 'Devices / ONU', 'description' => 'CPE inventory', 'icon' => 'bi-modem', 'accent' => 'orange', 'route' => 'onu-management'],
            ['slug' => 'purchase-orders', 'group' => 'Inventory', 'section' => 'Purchasing', 'label' => 'Purchase orders', 'description' => 'PO & GRN', 'icon' => 'bi-truck', 'accent' => 'orange', 'route' => 'admin.purchase-requests'],
            ['slug' => 'vendors', 'group' => 'Inventory', 'section' => 'Purchasing', 'label' => 'Vendors', 'description' => 'Suppliers', 'icon' => 'bi-shop', 'accent' => 'orange'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function finance(): array
    {
        return [
            ['slug' => 'finance-ops', 'group' => 'Finance', 'section' => 'Hub', 'label' => 'Finance operations', 'description' => 'Revenue · collections · GL · P&L', 'icon' => 'bi-calculator', 'accent' => 'fuchsia', 'route' => 'accounts-hub'],
            ['slug' => 'financial-reports', 'group' => 'Finance', 'section' => 'Reports', 'label' => 'Financial reports', 'description' => 'VAT & profit/loss', 'icon' => 'bi-graph-up', 'accent' => 'fuchsia', 'route' => 'admin.profit-summary'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function resellers(): array
    {
        return [
            ['slug' => 'partner-center', 'group' => 'Resellers', 'section' => 'Hub', 'label' => 'Partner center', 'description' => 'Franchise & commission', 'icon' => 'bi-shop-window', 'accent' => 'indigo', 'route' => 'admin.resellers.index'],
            ['slug' => 'all-partners', 'group' => 'Resellers', 'section' => 'Partners', 'label' => 'All partners', 'description' => 'Reseller accounts', 'icon' => 'bi-people', 'accent' => 'indigo', 'route' => 'admin.resellers.index'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function reports(): array
    {
        return [
            ['slug' => 'reports-center', 'group' => 'Reports', 'section' => 'Hub', 'label' => 'Reports center', 'description' => 'All analytics', 'icon' => 'bi-pie-chart', 'accent' => 'sky'],
            ['slug' => 'analytics-dashboard', 'group' => 'Reports', 'section' => 'Analytics', 'label' => 'Analytics dashboard', 'description' => 'KPIs & charts', 'icon' => 'bi-graph-up-arrow', 'accent' => 'sky', 'route' => 'ops-insights'],
            ['slug' => 'zone-collection', 'group' => 'Reports', 'section' => 'Collections', 'label' => 'Zone collection', 'description' => 'Recovery by zone', 'icon' => 'bi-geo', 'accent' => 'sky', 'route' => 'customer-summary'],
            ['slug' => 'btrc-dis', 'group' => 'Reports', 'section' => 'Regulatory', 'label' => 'BTRC DIS', 'description' => 'Export CSV', 'icon' => 'bi-file-earmark-arrow-down', 'accent' => 'sky', 'route' => 'dis-report'],
            ['slug' => 'monthly-billing-report', 'group' => 'Reports', 'section' => 'Billing', 'label' => 'Monthly billing', 'description' => 'Period reports', 'icon' => 'bi-calendar3', 'accent' => 'sky', 'route' => 'collection-report.index'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function system(): array
    {
        return [
            ['slug' => 'saas-sell', 'group' => 'System', 'section' => 'SaaS', 'label' => 'Sell ISP Admin', 'description' => 'Monthly/yearly · quotas · lock on unpaid', 'icon' => 'bi-bag-check', 'accent' => 'success', 'route' => 'admin.saas-operators', 'permission' => ['saas-sell']],
            ['slug' => 'staff-cash', 'group' => 'System', 'section' => 'SaaS', 'label' => 'Staff cash', 'description' => 'Collected · deposited · owes office', 'icon' => 'bi-cash-stack', 'accent' => 'success', 'route' => 'admin.staff-cash'],
            ['slug' => 'organization-center', 'group' => 'System', 'section' => 'Admin', 'label' => 'Organization center', 'description' => 'Staff · roles · security', 'icon' => 'bi-building', 'accent' => 'secondary', 'route' => 'site-settings'],
            ['slug' => 'backup-restore', 'group' => 'System', 'section' => 'Safety', 'label' => 'Backup & restore', 'description' => 'Download · upload restore', 'icon' => 'bi-cloud-arrow-down', 'accent' => 'success', 'route' => 'mikrotik-backup-setup'],
            ['slug' => 'api-configuration', 'group' => 'System', 'section' => 'Integrations', 'label' => 'API configuration', 'description' => 'REST tokens · HMAC', 'icon' => 'bi-key', 'accent' => 'violet'],
            ['slug' => 'performance-settings', 'group' => 'System', 'section' => 'Integrations', 'label' => 'Performance', 'description' => 'Polling · subscriber speed', 'icon' => 'bi-lightning', 'accent' => 'success'],
            ['slug' => 'integrations', 'group' => 'System', 'section' => 'Integrations', 'label' => 'Integrations', 'description' => 'Gateways & API keys', 'icon' => 'bi-puzzle', 'accent' => 'secondary', 'route' => 'site-settings'],
            ['slug' => 'automatic-processes', 'group' => 'System', 'section' => 'Automation', 'label' => 'Automatic processes', 'description' => 'Scheduled billing · sync · suspend', 'icon' => 'bi-clock', 'accent' => 'warning', 'route' => 'automatic-processes'],
            ['slug' => 'queue-monitor', 'group' => 'System', 'section' => 'Automation', 'label' => 'Queue monitor', 'description' => 'Workers · failed jobs', 'icon' => 'bi-stack', 'accent' => 'violet'],
            ['slug' => 'notifications-hub', 'group' => 'System', 'section' => 'Comms', 'label' => 'Notifications', 'description' => 'SMS · email · WhatsApp', 'icon' => 'bi-bell', 'accent' => 'secondary', 'route' => 'notifications'],
            ['slug' => 'whatsapp-bot', 'group' => 'System', 'section' => 'Comms', 'label' => 'WhatsApp bot', 'description' => 'MENU / BILL / SUPPORT', 'icon' => 'bi-whatsapp', 'accent' => 'success'],
            ['slug' => 'bulk-sms', 'group' => 'System', 'section' => 'Comms', 'label' => 'Bulk SMS', 'description' => 'Campaigns', 'icon' => 'bi-send', 'accent' => 'secondary', 'route' => 'sms-setup'],
            ['slug' => 'sms-templates', 'group' => 'System', 'section' => 'Comms', 'label' => 'SMS templates', 'description' => 'Message library', 'icon' => 'bi-chat-quote', 'accent' => 'secondary', 'route' => 'sms-setup'],
            ['slug' => 'sms-report', 'group' => 'System', 'section' => 'Comms', 'label' => 'SMS report', 'description' => 'Delivered · pending · failed', 'icon' => 'bi-envelope-check', 'accent' => 'secondary'],
            ['slug' => 'comms-hub', 'group' => 'System', 'section' => 'Comms', 'label' => 'Communication hub', 'description' => 'SMS service overview', 'icon' => 'bi-broadcast', 'accent' => 'secondary'],
            ['slug' => 'customer-portal-settings', 'group' => 'System', 'section' => 'Portal', 'label' => 'Customer portal', 'description' => 'Portal OTP & settings', 'icon' => 'bi-globe', 'accent' => 'secondary', 'route' => 'site-settings'],
            ['slug' => 'mobile-apps', 'group' => 'System', 'section' => 'Apps', 'label' => 'Mobile apps', 'description' => 'Technician API', 'icon' => 'bi-phone', 'accent' => 'secondary'],
        ];
    }
}
