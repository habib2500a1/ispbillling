<?php

namespace App\Support;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OperationsHub;
use App\Filament\Pages\SmsGatewaySetup;
use App\Filament\Pages\SubscriberListsHub;
use App\Filament\Resources\SalesLeadResource;
use App\Models\SalesLead;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

final class MobileDockPresenter
{
    /**
     * @return array{
     *     onDashboard: bool,
     *     onSubscribers: bool,
     *     onBilling: bool,
     *     onSms: bool,
     *     onNetwork: bool,
     *     onConnections: bool,
     *     newConnections: int,
     *     connectionsUrl: ?string,
     *     smsUrl: string,
     *     networkUrl: string,
     *     subscribersUrl: string,
     * }
     */
    public static function data(): array
    {
        $routeKey = request()->route()?->getName() ?? 'unknown';
        $tenantId = TenantResolver::currentTenantId() ?? 0;

        return Cache::remember('mobile_dock:'.$tenantId.':'.$routeKey, 300, static function () use ($tenantId): array {
            $onDashboard = request()->routeIs('filament.admin.pages.dashboard', 'filament.admin.pages.dashboard-hub');
            $onSubscribers = request()->routeIs(
                'filament.admin.pages.subscriber-lists-hub',
                'filament.admin.resources.subscribers.*',
            );
            $onBilling = request()->routeIs(
                'filament.admin.pages.bill-collection*',
                'filament.admin.pages.billing-overview',
                'filament.admin.pages.collector-mobile',
            );
            $onSms = request()->routeIs(
                'filament.admin.pages.sms-gateway',
                'filament.admin.pages.send-sms',
                'filament.admin.pages.notifications-hub',
                'filament.admin.pages.bulk-sms-campaign',
                'filament.admin.pages.manage-notifications',
                'filament.admin.resources.sms-delivery-reports.*',
                'filament.admin.resources.notification-logs.*',
            );
            $onNetwork = request()->routeIs(
                'filament.admin.pages.operations-hub',
                'filament.admin.pages.network-intelligence-hub',
                'filament.admin.pages.online-clients-monitoring',
                'filament.admin.pages.optical-monitoring-hub',
                'filament.admin.resources.mikrotik-servers.*',
            );
            $onConnections = request()->routeIs(
                'filament.admin.resources.sales-leads.*',
                'filament.admin.pages.sales-lead-pipeline',
            );

            return [
                'onDashboard' => $onDashboard,
                'onSubscribers' => $onSubscribers,
                'onBilling' => $onBilling,
                'onSms' => $onSms,
                'onNetwork' => $onNetwork,
                'onConnections' => $onConnections,
                'newConnections' => (int) Cache::remember('mobile_dock_new_leads:'.$tenantId, 120, static fn (): int => SalesLead::query()
                    ->where('status', SalesLead::STATUS_NEW)
                    ->count()),
                'connectionsUrl' => SalesLeadPanelAccess::canView()
                    ? SalesLeadResource::getUrl()
                    : null,
                'smsUrl' => AdminNavUrl::for(SmsGatewaySetup::class),
                'networkUrl' => AdminNavUrl::for(OperationsHub::class),
                'subscribersUrl' => AdminNavUrl::for(SubscriberListsHub::class),
                'dashboardUrl' => Dashboard::getUrl(),
                'billingUrl' => BillCollectionDesk::getUrl(),
            ];
        });
    }
}
