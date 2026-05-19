<?php

namespace App\Support;

use App\Filament\Pages\AccountingHub;
use App\Filament\Pages\AnalyticsReports;
use App\Filament\Pages\BandwidthMonitor;
use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\BillingReports;
use App\Filament\Pages\ChurnZoneReports;
use App\Filament\Pages\CollectionDeskReport;
use App\Filament\Pages\CollectorCashHub;
use App\Filament\Pages\CollectorMobile;
use App\Filament\Pages\CollectorVisitsReport;
use App\Filament\Pages\DashboardHub;
use App\Filament\Pages\DunningReport;
use App\Filament\Pages\GatewayReconciliationReport;
use App\Filament\Pages\BtrcReport;
use App\Filament\Pages\HrPayrollHub;
use App\Filament\Pages\ManagePlatformBackups;
use App\Filament\Pages\InventoryHub;
use App\Filament\Auth\EditAdminProfile;
use App\Filament\Pages\ManageAppSettings;
use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\ManageCompanySetup;
use App\Filament\Pages\NetworkIntelligenceHub;
use App\Filament\Pages\NetworkTopology;
use App\Filament\Pages\NotificationsHub;
use App\Filament\Pages\OperationsHub;
use App\Filament\Pages\PaymentsOverview;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\ResellersHub;
use App\Filament\Pages\StaffControlHub;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\OltResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\SupportTicketResource;

class AdminCommandPalette
{
    /**
     * @return list<array{label: string, group: string, url: string}>
     */
    public static function items(): array
    {
        return [
            ['group' => 'Overview', 'label' => 'All modules', 'url' => OperationsHub::getUrl()],
            ['group' => 'Overview', 'label' => 'Dashboard hub (NOC / Billing / Support)', 'url' => DashboardHub::getUrl()],
            ['group' => 'Billing', 'label' => 'Billing overview', 'url' => BillingOverview::getUrl()],
            ['group' => 'Billing', 'label' => 'Bill collection desk', 'url' => BillCollectionDesk::getUrl()],
            ['group' => 'Billing', 'label' => 'Collection report', 'url' => CollectionDeskReport::getUrl()],
            ['group' => 'Billing', 'label' => 'Collector visits (GPS map)', 'url' => CollectorVisitsReport::getUrl()],
            ['group' => 'Billing', 'label' => 'Collector mobile (GPS)', 'url' => CollectorMobile::getUrl()],
            ['group' => 'Billing', 'label' => 'Dunning report', 'url' => DunningReport::getUrl()],
            ['group' => 'Payments', 'label' => 'Gateway reconciliation', 'url' => GatewayReconciliationReport::getUrl()],
            ['group' => 'Billing', 'label' => 'Invoices', 'url' => InvoiceResource::getUrl('index')],
            ['group' => 'Billing', 'label' => 'Payments', 'url' => PaymentResource::getUrl('index')],
            ['group' => 'Billing', 'label' => 'Payments hub', 'url' => PaymentsOverview::getUrl()],
            ['group' => 'Payments', 'label' => 'Collector settlement', 'url' => CollectorCashHub::getUrl()],
            ['group' => 'Subscribers', 'label' => 'Customers', 'url' => CustomerResource::getUrl('index')],
            ['group' => 'Support', 'label' => 'Support hub', 'url' => SupportHub::getUrl()],
            ['group' => 'Support', 'label' => 'Tickets', 'url' => SupportTicketResource::getUrl('index')],
            ['group' => 'Network', 'label' => 'Bandwidth monitor', 'url' => BandwidthMonitor::getUrl()],
            ['group' => 'Network', 'label' => 'Network intelligence', 'url' => NetworkIntelligenceHub::getUrl()],
            ['group' => 'Network', 'label' => 'Network topology', 'url' => NetworkTopology::getUrl()],
            ['group' => 'Network', 'label' => 'OLT / ONU', 'url' => OltResource::getUrl('index')],
            ['group' => 'HR', 'label' => 'HR & payroll', 'url' => HrPayrollHub::getUrl()],
            ['group' => 'Inventory', 'label' => 'Inventory hub', 'url' => InventoryHub::getUrl()],
            ['group' => 'Reports', 'label' => 'Reports hub', 'url' => ReportsHub::getUrl()],
            ['group' => 'Reports', 'label' => 'Monthly billing reports', 'url' => BillingReports::getUrl()],
            ['group' => 'Reports', 'label' => 'Zone churn & collection', 'url' => ChurnZoneReports::getUrl()],
            ['group' => 'Reports', 'label' => 'BTRC DIS export', 'url' => BtrcReport::getUrl()],
            ['group' => 'Reports', 'label' => 'Analytics', 'url' => AnalyticsReports::getUrl()],
            ['group' => 'Finance', 'label' => 'Accounting', 'url' => AccountingHub::getUrl()],
            ['group' => 'Resellers', 'label' => 'Reseller hub', 'url' => ResellersHub::getUrl()],
            ['group' => 'Admin', 'label' => 'My account & password', 'url' => EditAdminProfile::getUrl()],
            ['group' => 'Admin', 'label' => 'Staff control', 'url' => StaffControlHub::getUrl()],
            ['group' => 'System', 'label' => 'Backup & restore', 'url' => ManagePlatformBackups::getUrl()],
            ['group' => 'Admin', 'label' => 'Notifications', 'url' => NotificationsHub::getUrl()],
            ['group' => 'System', 'label' => 'Company setup', 'url' => ManageCompanySetup::getUrl()],
            ['group' => 'System', 'label' => 'App settings', 'url' => ManageAppSettings::getUrl()],
            ['group' => 'Network', 'label' => 'Laser thresholds', 'url' => ManageOpticalLaserSettings::getUrl()],
        ];
    }
}
