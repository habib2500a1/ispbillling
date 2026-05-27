<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\CustomerResource;
use App\Services\Clients\ClientsDashboardService;
use App\Support\TenantResolver;
use Filament\Pages\Page;

class ClientsHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static string $view = 'filament.pages.clients-hub';

    protected static ?string $slug = 'clients-hub';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    public function mount(): void
    {
        ClientsDashboardService::flushSummaryCache(TenantResolver::requiredTenantId());
        $this->hubStatsCache = null;
    }

    public function refreshLiveData(): void
    {
        if ((int) config('bandwidth.live_page_poll_seconds', 60) <= 0) {
            return;
        }

        ClientsDashboardService::flushSummaryCache(TenantResolver::requiredTenantId());
        $this->hubStatsCache = null;
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(
            fn (): array => app(ClientsDashboardService::class)->summary(),
        );
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getStats();

        return [
            [
                'label' => 'Total',
                'value' => number_format($stats['total'] ?? 0),
                'hint' => 'Active directory',
            ],
            [
                'label' => 'Online',
                'value' => number_format($stats['online'] ?? 0),
                'hint' => 'PPP live now',
            ],
            [
                'label' => 'Active',
                'value' => number_format($stats['active'] ?? 0),
                'hint' => 'In good standing',
            ],
            [
                'label' => 'Expired',
                'value' => number_format($stats['expired'] ?? 0),
                'hint' => 'Needs renewal',
            ],
            [
                'label' => 'Suspended',
                'value' => number_format($stats['suspended'] ?? 0),
                'hint' => 'Disconnected',
            ],
        ];
    }

    /**
     * @return list<array{label: string, count: int|string, url: string, icon: string, tone: string}>
     */
    public function getQuickFilters(): array
    {
        $stats = $this->getStats();
        $index = CustomerResource::getUrl('index');

        return [
            [
                'label' => 'All clients',
                'count' => $stats['total'] ?? 0,
                'url' => $index,
                'icon' => 'heroicon-o-users',
                'tone' => 'teal',
            ],
            [
                'label' => 'Online',
                'count' => $stats['online'] ?? 0,
                'url' => $index.'?preset=online',
                'icon' => 'heroicon-o-signal',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Active',
                'count' => $stats['active'] ?? 0,
                'url' => CustomerResource::getUrl('active'),
                'icon' => 'heroicon-o-check-circle',
                'tone' => 'sky',
            ],
            [
                'label' => 'Expire 3 days',
                'count' => '—',
                'url' => CustomerResource::getUrl('expire-3'),
                'icon' => 'heroicon-o-bell-alert',
                'tone' => 'amber',
            ],
            [
                'label' => 'Expired',
                'count' => $stats['expired'] ?? 0,
                'url' => CustomerResource::getUrl('expired'),
                'icon' => 'heroicon-o-exclamation-circle',
                'tone' => 'rose',
            ],
            [
                'label' => 'Suspended',
                'count' => $stats['suspended'] ?? 0,
                'url' => CustomerResource::getUrl('suspended'),
                'icon' => 'heroicon-o-pause-circle',
                'tone' => 'orange',
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, icon_class: string, featured?: bool}>
     */
    public function getActionCards(): array
    {
        $cards = [
            [
                'title' => 'All clients',
                'desc' => 'Search, filter, bulk SMS, export & package changes.',
                'url' => CustomerResource::getUrl('index'),
                'icon' => 'heroicon-o-users',
                'icon_class' => 'text-teal-600',
            ],
            [
                'title' => 'Add client',
                'desc' => 'New subscriber — package, PPPoE, billing day & area.',
                'url' => CustomerResource::getUrl('create'),
                'icon' => 'heroicon-o-user-plus',
                'icon_class' => 'text-emerald-600',
                'featured' => true,
            ],
            [
                'title' => 'Live PPP monitor',
                'desc' => 'Sessions, traffic graphs, disconnect & diagnostics.',
                'url' => OnlineClientsMonitoring::getUrl(),
                'icon' => 'heroicon-o-bolt',
                'icon_class' => 'text-cyan-600',
            ],
            [
                'title' => "Today's renewals",
                'desc' => 'Billing day matches today — collect before disconnect.',
                'url' => CustomerResource::getUrl('today'),
                'icon' => 'heroicon-o-calendar-days',
                'icon_class' => 'text-amber-600',
            ],
            [
                'title' => 'Pending activation',
                'desc' => 'New sign-ups waiting for install or payment.',
                'url' => CustomerResource::getUrl('pending'),
                'icon' => 'heroicon-o-clock',
                'icon_class' => 'text-violet-600',
            ],
            [
                'title' => 'Left clients',
                'desc' => 'Terminated / archived accounts history.',
                'url' => CustomerResource::getUrl('left'),
                'icon' => 'heroicon-o-archive-box',
                'icon_class' => 'text-slate-600',
            ],
        ];

        if (ImportClientsCsvPage::canAccess()) {
            $cards[] = [
                'title' => 'Import CSV',
                'desc' => 'Bulk onboard from spreadsheet with field mapping.',
                'url' => ImportClientsCsvPage::getUrl(),
                'icon' => 'heroicon-o-arrow-up-tray',
                'icon_class' => 'text-fuchsia-600',
            ];
        }

        if (ExportClientsReport::canAccess()) {
            $cards[] = [
                'title' => 'Export clients',
                'desc' => 'Download CSV for audit, BTRC, or external billing.',
                'url' => ExportClientsReport::getUrl(),
                'icon' => 'heroicon-o-arrow-down-tray',
                'icon_class' => 'text-sky-600',
            ];
        }

        if (AreaWiseClientsReport::canAccess()) {
            $cards[] = [
                'title' => 'Area-wise report',
                'desc' => 'Subscribers grouped by area, zone & subzone.',
                'url' => AreaWiseClientsReport::getUrl(),
                'icon' => 'heroicon-o-map',
                'icon_class' => 'text-indigo-600',
            ];
        }

        return $cards;
    }

    public static function canAccess(): bool
    {
        return CustomerResource::canViewAny();
    }
}
