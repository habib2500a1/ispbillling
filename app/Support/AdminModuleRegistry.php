<?php

namespace App\Support;

use App\Support\Rbac\StaffCapability;
use App\Filament\Pages\AccountingHub;
use App\Filament\Pages\AnalyticsReports;
use App\Filament\Pages\BandwidthMonitor;
use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\BillingReports;
use App\Filament\Pages\BroadcastOutage;
use App\Filament\Pages\BulkSmsCampaign;
use App\Filament\Pages\BtrcReport;
use App\Filament\Pages\ChurnZoneReports;
use App\Filament\Pages\FinancialReports;
use App\Filament\Pages\HrPayrollHub;
use App\Filament\Pages\InventoryHub;
use App\Filament\Pages\ManageAppSettings;
use App\Filament\Pages\ManagePlatformBackups;
use App\Filament\Pages\ManagePortalSettings;
use App\Filament\Pages\MobileAppsHub;
use App\Filament\Pages\NetflowAnalysis;
use App\Filament\Pages\NetworkIntelligenceHub;
use App\Filament\Pages\NetworkTopology;
use App\Filament\Pages\SnmpMonitor;
use App\Filament\Pages\NotificationsHub;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\PaymentsOverview;
use App\Filament\Pages\RadiusUserAdmin;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\WhatsAppBotHub;
use App\Filament\Pages\ResellersHub;
use App\Filament\Pages\SalesLeadPipeline;
use App\Filament\Pages\StaffControlHub;
use App\Filament\Pages\SubscriberListsHub;
use App\Filament\Pages\SupportHub;
use App\Filament\Pages\TaskKanbanBoard;
use App\Filament\Resources\AreaResource;
use App\Filament\Resources\AutomaticProcessResource;
use App\Filament\Resources\BranchResource;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\HotspotVoucherResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\IpPoolResource;
use App\Filament\Resources\MikrotikServerResource;
use App\Filament\Resources\OltResource;
use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PayrollRunResource;
use App\Filament\Resources\PopBoxResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\ResellerResource;
use App\Filament\Resources\SalesLeadResource;
use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\ZoneResource;

class AdminModuleRegistry
{
    /**
     * @return list<array{group: string, section: string, label: string, description: string, url: string, accent: string, icon?: string}>
     */
    public static function all(): array
    {
        return [
            // ── Subscribers ──
            ['group' => 'Subscribers', 'section' => 'Main', 'label' => 'All subscribers', 'description' => 'Search, edit, PPPoE & billing', 'url' => CustomerResource::getUrl('index'), 'accent' => 'text-teal-600', 'icon' => 'heroicon-o-users'],
            ['group' => 'Subscribers', 'section' => 'Lists', 'label' => 'Subscriber lists', 'description' => 'Free · VIP · expired · suspended', 'url' => SubscriberListsHub::getUrl(), 'accent' => 'text-teal-600', 'icon' => 'heroicon-o-queue-list'],
            ['group' => 'Subscribers', 'section' => 'Catalog', 'label' => 'Packages', 'description' => 'Plans, speed & pricing', 'url' => PackageResource::getUrl('index'), 'accent' => 'text-teal-600', 'icon' => 'heroicon-o-rectangle-stack'],
            ['group' => 'Subscribers', 'section' => 'Coverage', 'label' => 'Zones & areas', 'description' => 'Area → zone → subzone', 'url' => ZoneResource::getUrl('index'), 'accent' => 'text-teal-600', 'icon' => 'heroicon-o-map'],

            // ── Billing ──
            ['group' => 'Billing', 'section' => 'Hub', 'label' => 'Billing center', 'description' => 'Open bills · overdue · collections', 'url' => BillingOverview::getUrl(), 'accent' => 'text-violet-600', 'icon' => 'heroicon-o-banknotes'],
            ['group' => 'Billing', 'section' => 'Collections', 'label' => 'Bill collection desk', 'description' => 'Search subscriber · collect payment', 'url' => BillCollectionDesk::getUrl(), 'accent' => 'text-emerald-600', 'icon' => 'heroicon-o-currency-bangladeshi'],
            ['group' => 'Billing', 'section' => 'Documents', 'label' => 'Invoices', 'description' => 'Generate, print, due dates', 'url' => InvoiceResource::getUrl('index'), 'accent' => 'text-violet-600', 'icon' => 'heroicon-o-document-text'],
            ['group' => 'Billing', 'section' => 'Promotions', 'label' => 'Coupons', 'description' => 'Discount codes', 'url' => CouponResource::getUrl('index'), 'accent' => 'text-violet-600', 'icon' => 'heroicon-o-ticket'],

            // ── Payments ──
            ['group' => 'Payments', 'section' => 'Hub', 'label' => 'Payment center', 'description' => 'bKash · Nagad · SSLCommerz', 'url' => PaymentsOverview::getUrl(), 'accent' => 'text-emerald-600', 'icon' => 'heroicon-o-credit-card'],
            ['group' => 'Payments', 'section' => 'Records', 'label' => 'Collections', 'description' => 'Receipts & payment log', 'url' => PaymentResource::getUrl('index'), 'accent' => 'text-emerald-600', 'icon' => 'heroicon-o-banknotes'],

            // ── Network ──
            ['group' => 'Network', 'section' => 'Hub', 'label' => 'Network center', 'description' => 'SNMP · NetFlow · optical', 'url' => NetworkIntelligenceHub::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-cpu-chip'],
            ['group' => 'Network', 'section' => 'Map', 'label' => 'Topology map', 'description' => 'MikroTik → OLT → PON → ONU', 'url' => NetworkTopology::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-share'],
            ['group' => 'Network', 'section' => 'Core', 'label' => 'MikroTik', 'description' => 'Routers & PPPoE sync', 'url' => MikrotikServerResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-server'],
            ['group' => 'Network', 'section' => 'Core', 'label' => 'RADIUS users', 'description' => 'radcheck / radusergroup admin', 'url' => RadiusUserAdmin::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-circle-stack'],
            ['group' => 'Network', 'section' => 'Fiber', 'label' => 'OLT / GPON', 'description' => 'ONU inventory & ports', 'url' => OltResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-signal'],
            ['group' => 'Network', 'section' => 'Monitor', 'label' => 'Online clients', 'description' => 'Live PPP sessions', 'url' => OnlineClientsMonitoring::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-signal'],
            ['group' => 'Network', 'section' => 'Monitor', 'label' => 'Bandwidth', 'description' => 'Usage & abuse alerts', 'url' => BandwidthMonitor::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-chart-bar'],
            ['group' => 'Network', 'section' => 'Monitor', 'label' => 'SNMP monitor', 'description' => 'Poll logs & interface status', 'url' => SnmpMonitor::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-signal'],
            ['group' => 'Network', 'section' => 'Monitor', 'label' => 'NetFlow analysis', 'description' => 'Top talkers & protocols', 'url' => NetflowAnalysis::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-arrows-right-left'],
            ['group' => 'Network', 'section' => 'Fiber', 'label' => 'Optical NOC', 'description' => 'dBm levels & alarms', 'url' => OpticalMonitoringHub::getUrl(), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-bolt'],
            ['group' => 'Network', 'section' => 'Fiber', 'label' => 'Laser thresholds', 'description' => 'RX/TX dBm bands & high laser limits', 'url' => ManageOpticalLaserSettings::getUrl(), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-adjustments-vertical'],
            ['group' => 'Network', 'section' => 'Infrastructure', 'label' => 'POP / boxes', 'description' => 'Sites & capacity', 'url' => PopBoxResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-building-office-2'],
            ['group' => 'Network', 'section' => 'Coverage', 'label' => 'Areas', 'description' => 'Coverage map', 'url' => AreaResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-map-pin'],
            ['group' => 'Network', 'section' => 'IPAM', 'label' => 'IP pools', 'description' => 'Static IP allocation', 'url' => IpPoolResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-globe-alt'],
            ['group' => 'Network', 'section' => 'Hotspot', 'label' => 'Hotspot vouchers', 'description' => 'Prepaid Wi‑Fi cards', 'url' => HotspotVoucherResource::getUrl('index'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-ticket'],
            ['group' => 'Network', 'section' => 'Hotspot', 'label' => 'Hotspot portal', 'description' => 'Captive voucher login', 'url' => url('/hotspot'), 'accent' => 'text-cyan-600', 'icon' => 'heroicon-o-wifi'],

            // ── Support ──
            ['group' => 'Support', 'section' => 'Hub', 'label' => 'Support center', 'description' => 'Tickets · SLA · chat', 'url' => SupportHub::getUrl(), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-lifebuoy'],
            ['group' => 'Support', 'section' => 'CRM', 'label' => 'New connections', 'description' => 'Website signup requests · convert to subscriber', 'url' => SalesLeadResource::getUrl('index'), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-user-plus'],
            ['group' => 'Support', 'section' => 'CRM', 'label' => 'Connection pipeline', 'description' => 'Kanban by stage', 'url' => SalesLeadPipeline::getUrl(), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-view-columns'],
            ['group' => 'Support', 'section' => 'Tickets', 'label' => 'All tickets', 'description' => 'Complaints queue', 'url' => SupportTicketResource::getUrl('index'), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-chat-bubble-left-right'],
            ['group' => 'Support', 'section' => 'Tasks', 'label' => 'Task board', 'description' => 'Kanban for staff', 'url' => TaskKanbanBoard::getUrl(), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-view-columns'],
            ['group' => 'Support', 'section' => 'Alerts', 'label' => 'Outage broadcast', 'description' => 'SMS / email alerts', 'url' => BroadcastOutage::getUrl(), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-megaphone'],

            // ── HR ──
            ['group' => 'HR & Payroll', 'section' => 'Hub', 'label' => 'HR center', 'description' => 'Overview & payroll', 'url' => HrPayrollHub::getUrl(), 'accent' => 'text-rose-600', 'icon' => 'heroicon-o-briefcase'],
            ['group' => 'HR & Payroll', 'section' => 'Staff', 'label' => 'Employees', 'description' => 'Staff profiles', 'url' => EmployeeResource::getUrl('index'), 'accent' => 'text-rose-600', 'icon' => 'heroicon-o-user'],
            ['group' => 'HR & Payroll', 'section' => 'Payroll', 'label' => 'Payroll runs', 'description' => 'Salary processing', 'url' => PayrollRunResource::getUrl('index'), 'accent' => 'text-rose-600', 'icon' => 'heroicon-o-currency-dollar'],

            // ── Inventory ──
            ['group' => 'Inventory', 'section' => 'Hub', 'label' => 'Inventory center', 'description' => 'Stock overview', 'url' => InventoryHub::getUrl(), 'accent' => 'text-orange-600', 'icon' => 'heroicon-o-cube'],
            ['group' => 'Inventory', 'section' => 'Stock', 'label' => 'Products', 'description' => 'SKU & pricing', 'url' => ProductResource::getUrl('index'), 'accent' => 'text-orange-600', 'icon' => 'heroicon-o-shopping-bag'],
            ['group' => 'Inventory', 'section' => 'Stock', 'label' => 'Devices / ONU', 'description' => 'CPE inventory', 'url' => DeviceResource::getUrl('index'), 'accent' => 'text-orange-600', 'icon' => 'heroicon-o-wifi'],
            ['group' => 'Inventory', 'section' => 'Purchasing', 'label' => 'Purchase orders', 'description' => 'PO & GRN', 'url' => PurchaseOrderResource::getUrl('index'), 'accent' => 'text-orange-600', 'icon' => 'heroicon-o-truck'],
            ['group' => 'Inventory', 'section' => 'Purchasing', 'label' => 'Vendors', 'description' => 'Suppliers', 'url' => VendorResource::getUrl('index'), 'accent' => 'text-orange-600', 'icon' => 'heroicon-o-building-storefront'],

            // ── Finance ──
            ['group' => 'Finance', 'section' => 'Hub', 'label' => 'Accounting', 'description' => 'Ledger · cashbook · P&L', 'url' => AccountingHub::getUrl(), 'accent' => 'text-fuchsia-600', 'icon' => 'heroicon-o-calculator'],
            ['group' => 'Finance', 'section' => 'Reports', 'label' => 'Financial reports', 'description' => 'VAT & profit/loss', 'url' => FinancialReports::getUrl(), 'accent' => 'text-fuchsia-600', 'icon' => 'heroicon-o-document-chart-bar'],

            // ── Resellers ──
            ['group' => 'Resellers', 'section' => 'Hub', 'label' => 'Partner center', 'description' => 'Franchise & commission', 'url' => ResellersHub::getUrl(), 'accent' => 'text-indigo-600', 'icon' => 'heroicon-o-building-storefront'],
            ['group' => 'Resellers', 'section' => 'Partners', 'label' => 'All partners', 'description' => 'Reseller accounts', 'url' => ResellerResource::getUrl('index'), 'accent' => 'text-indigo-600', 'icon' => 'heroicon-o-user-group'],

            // ── Reports ──
            ['group' => 'Reports', 'section' => 'Hub', 'label' => 'Reports center', 'description' => 'All analytics', 'url' => ReportsHub::getUrl(), 'accent' => 'text-sky-600', 'icon' => 'heroicon-o-chart-pie'],
            ['group' => 'Reports', 'section' => 'Analytics', 'label' => 'Analytics dashboard', 'description' => 'KPIs & charts', 'url' => AnalyticsReports::getUrl(), 'accent' => 'text-sky-600', 'icon' => 'heroicon-o-presentation-chart-line'],
            ['group' => 'Reports', 'section' => 'Collections', 'label' => 'Zone collection', 'description' => 'Recovery by zone', 'url' => ChurnZoneReports::getUrl(), 'accent' => 'text-sky-600', 'icon' => 'heroicon-o-map'],
            ['group' => 'Reports', 'section' => 'Regulatory', 'label' => 'BTRC DIS', 'description' => 'Export CSV', 'url' => BtrcReport::getUrl(), 'accent' => 'text-sky-600', 'icon' => 'heroicon-o-document-arrow-down'],
            ['group' => 'Reports', 'section' => 'Billing', 'label' => 'Monthly billing', 'description' => 'Period reports', 'url' => BillingReports::getUrl(), 'accent' => 'text-sky-600', 'icon' => 'heroicon-o-calendar'],

            // ── System ──
            ['group' => 'System', 'section' => 'Admin', 'label' => 'Staff & security', 'description' => 'Users · roles · 2FA', 'url' => StaffControlHub::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-shield-check'],
            ['group' => 'System', 'section' => 'Safety', 'label' => 'Backup & restore', 'description' => 'Download ZIP · upload restore', 'url' => ManagePlatformBackups::getUrl(), 'accent' => 'text-emerald-600', 'icon' => 'heroicon-o-archive-box-arrow-down'],
            ['group' => 'System', 'section' => 'Integrations', 'label' => 'Integrations', 'description' => 'Gateways & API keys', 'url' => ManageAppSettings::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-puzzle-piece'],
            ['group' => 'System', 'section' => 'Automation', 'label' => 'Automatic process', 'description' => 'Scheduled billing · sync · suspend', 'url' => AutomaticProcessResource::getUrl('index'), 'accent' => 'text-amber-600', 'icon' => 'heroicon-o-clock'],
            ['group' => 'System', 'section' => 'Comms', 'label' => 'Notifications', 'description' => 'SMS · email · WhatsApp', 'url' => NotificationsHub::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-bell-alert'],
            ['group' => 'System', 'section' => 'Comms', 'label' => 'WhatsApp bot', 'description' => 'Two-way MENU / BILL / SUPPORT', 'url' => WhatsAppBotHub::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-chat-bubble-left-right'],
            ['group' => 'System', 'section' => 'Comms', 'label' => 'Bulk SMS', 'description' => 'Campaigns', 'url' => BulkSmsCampaign::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-paper-airplane'],
            ['group' => 'System', 'section' => 'Portal', 'label' => 'Customer portal', 'description' => 'Portal OTP & settings', 'url' => ManagePortalSettings::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-globe-alt'],
            ['group' => 'System', 'section' => 'Portal', 'label' => 'Public /pay', 'description' => 'Bill payment page', 'url' => url('/pay'), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-credit-card'],
            ['group' => 'System', 'section' => 'Portal', 'label' => 'Public signup', 'description' => 'New connection requests', 'url' => url('/portal/signup'), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-user-plus'],
            ['group' => 'System', 'section' => 'Org', 'label' => 'Branches', 'description' => 'Multi-branch', 'url' => BranchResource::getUrl('index'), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-building-office'],
            ['group' => 'System', 'section' => 'Apps', 'label' => 'Mobile apps', 'description' => 'Technician API', 'url' => MobileAppsHub::getUrl(), 'accent' => 'text-slate-600', 'icon' => 'heroicon-o-device-phone-mobile'],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    /**
     * Modules the current user may open (permission-based, not role slug).
     *
     * @return list<array{group: string, section: string, label: string, description: string, url: string, accent: string, icon?: string}>
     */
    public static function visible(): array
    {
        return array_values(array_filter(
            static::all(),
            fn (array $module): bool => StaffCapability::for(auth()->user())->canAccessModuleGroup($module['group'] ?? ''),
        ));
    }

    public static function groupedBySection(?string $groupFilter = null): array
    {
        $items = collect(static::visible());
        if ($groupFilter !== null) {
            $items = $items->where('group', $groupFilter);
        }

        return $items
            ->groupBy('section')
            ->map(fn ($sectionItems) => $sectionItems->values()->all())
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return collect(static::visible())->pluck('group')->unique()->values()->all();
    }

    public static function iconForGroup(string $group): string
    {
        return match ($group) {
            'Overview' => 'heroicon-o-squares-2x2',
            'Subscribers' => 'heroicon-o-user-group',
            'Billing' => 'heroicon-o-document-text',
            'Payments' => 'heroicon-o-banknotes',
            'Network' => 'heroicon-o-signal',
            'Support' => 'heroicon-o-lifebuoy',
            'HR & Payroll' => 'heroicon-o-briefcase',
            'Inventory' => 'heroicon-o-cube',
            'Finance' => 'heroicon-o-calculator',
            'Resellers' => 'heroicon-o-building-storefront',
            'Reports' => 'heroicon-o-chart-pie',
            default => 'heroicon-o-squares-2x2',
        };
    }

    public static function iconForModule(array $mod): string
    {
        return $mod['icon'] ?? static::iconForGroup($mod['group']);
    }
}
