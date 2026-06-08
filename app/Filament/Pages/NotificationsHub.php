<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\SmsTemplateResource;
use App\Services\Notifications\CommsHubDashboardService;
use App\Support\SmsSidebarRegistry;
use Filament\Pages\Page;

class NotificationsHub extends Page
{
    use HidesHubNavigation;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notifications-hub';

    protected static ?string $navigationLabel = 'Communication hub';

    protected static ?string $title = 'Communication Command Center';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public array $dashboard = [];

    public string $searchQuery = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'dashboard';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshDashboard();
    }

    public function refreshDashboard(): void
    {
        $this->dashboard = app(CommsHubDashboardService::class)->snapshot();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(CommsHubDashboardService::class)->search($this->searchQuery);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNavLinks(): array
    {
        return SmsSidebarRegistry::definitions();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $d = $this->dashboard;
        $analytics = $d['analytics'] ?? ['labels' => [], 'sent' => [], 'failed' => []];

        return [
            'd' => $d,
            'kpis' => $d['kpis'] ?? [],
            'channels' => $d['channels'] ?? [],
            'billing' => $d['billing_automation'] ?? [],
            'analytics' => $analytics,
            'maxBar' => max(array_merge($analytics['sent'] ?? [1], [1])),
            'kpiCards' => [
                ['key' => 'sms_today', 'label' => 'SMS today', 'class' => 'isp-comms-kpi--sms'],
                ['key' => 'whatsapp_today', 'label' => 'WhatsApp today', 'class' => 'isp-comms-kpi--wa'],
                ['key' => 'email_today', 'label' => 'Email today', 'class' => 'isp-comms-kpi--email'],
                ['key' => 'push_today', 'label' => 'Push today', 'class' => 'isp-comms-kpi--push'],
                ['key' => 'failed_24h', 'label' => 'Failed 24h', 'class' => 'isp-comms-kpi--fail'],
                ['key' => 'scheduled', 'label' => 'Scheduled', 'class' => 'isp-comms-kpi--sched'],
                ['key' => 'active_campaigns', 'label' => 'Active campaigns', 'class' => 'isp-comms-kpi--camp'],
                ['key' => 'delivery_rate', 'label' => 'Delivery rate %', 'class' => 'isp-comms-kpi--rate', 'suffix' => '%'],
            ],
            'quickActions' => [
                ['url' => SendSms::getUrl(), 'title' => 'Send SMS', 'desc' => 'Single subscriber'],
                ['url' => BulkSmsCampaign::getUrl(), 'title' => 'Bulk campaign', 'desc' => 'Targeted blast'],
                ['url' => BroadcastOutage::getUrl(), 'title' => 'Outage broadcast', 'desc' => 'Maintenance notice'],
                ['url' => SmsTemplateResource::getUrl(), 'title' => 'Templates', 'desc' => 'Message library'],
                ['url' => SmsGatewaySetup::getUrl(), 'title' => 'SMS gateway', 'desc' => 'Balance & test'],
                ['url' => WhatsAppBotHub::getUrl(), 'title' => 'WhatsApp', 'desc' => 'Bot & Cloud API'],
                ['url' => ManageNotifications::getUrl(), 'title' => 'All settings', 'desc' => 'Channels & events'],
                ['url' => FiberPlantMap::getUrl(), 'title' => 'GIS targeting', 'desc' => 'Map → bulk audience'],
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canSms();
    }
}
