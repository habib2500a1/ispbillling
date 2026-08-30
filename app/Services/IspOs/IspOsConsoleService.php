<?php

namespace App\Services\IspOs;

use App\Models\AutomaticProcess;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\Olt;
use App\Models\PackageList;
use App\Models\RouterList;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Local-only ISP OS console snapshot. No MikroTik / OLT API calls.
 */
final class IspOsConsoleService
{
    /**
     * @return array{
     *   subscribers: int,
     *   active: int,
     *   routers: int,
     *   olts: int,
     *   packages: int,
     *   open_tickets: int,
     *   today_collection: float,
     *   jobs_on: int,
     *   jobs_total: int,
     *   env: string,
     *   generated_at: string,
     *   quick: list<array{label: string, url: string, icon: string}>
     * }
     */
    public function snapshot(): array
    {
        $subscribers = 0;
        $active = 0;
        $routers = 0;
        $olts = 0;
        $packages = 0;
        $openTickets = 0;
        $todayCollection = 0.0;
        $jobsOn = 0;
        $jobsTotal = 0;

        try {
            if (Schema::hasTable('customers_infos')) {
                $subscribers = CustomersInfo::query()->count();
                $active = CustomersInfo::query()->where('status', 'active')->count();
            }
            if (Schema::hasTable('router_lists')) {
                $routers = RouterList::query()->count();
            }
            if (Schema::hasTable('olts')) {
                $olts = Olt::query()->count();
            }
            if (Schema::hasTable('package_lists')) {
                $packages = PackageList::query()->count();
            }
            if (Schema::hasTable('support_tickets')) {
                $openTickets = SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count();
            }
            if (Schema::hasTable('collection_summaries')) {
                $todayCollection = round((float) CollectionSummary::query()
                    ->whereDate('collection_date', today())
                    ->sum('collection_amount'), 2);
            }
            if (Schema::hasTable('automatic_processes')) {
                $jobsTotal = AutomaticProcess::query()->count();
                $jobsOn = AutomaticProcess::query()->where('enabled', true)->count();
            }
        } catch (\Throwable) {
            // Console still renders with zeros.
        }

        return [
            'subscribers' => $subscribers,
            'active' => $active,
            'routers' => $routers,
            'olts' => $olts,
            'packages' => $packages,
            'open_tickets' => $openTickets,
            'today_collection' => $todayCollection,
            'jobs_on' => $jobsOn,
            'jobs_total' => $jobsTotal,
            'env' => (string) config('app.env', 'production'),
            'generated_at' => now()->timezone((string) config('app.timezone', 'Asia/Dhaka'))->format('d M Y · H:i'),
            'quick' => $this->quickActions(),
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string}>
     */
    private function quickActions(): array
    {
        $items = [
            ['label' => 'New subscriber', 'route' => 'new-customer', 'icon' => 'bi-person-plus'],
            ['label' => 'Collect payment', 'route' => 'payment-collection', 'icon' => 'bi-currency-exchange'],
            ['label' => 'Subscribers', 'route' => 'customers.index', 'icon' => 'bi-people'],
            ['label' => 'MikroTik', 'route' => 'mikrotik-sync', 'icon' => 'bi-router'],
            ['label' => 'OLT', 'route' => 'olt-management', 'icon' => 'bi-hdd-network'],
            ['label' => 'Tickets', 'route' => 'admin-tickets', 'icon' => 'bi-life-preserver'],
            ['label' => 'Admin Center', 'route' => 'admin-center', 'icon' => 'bi-sliders2'],
        ];

        $out = [];
        foreach ($items as $item) {
            if (! Route::has($item['route'])) {
                continue;
            }
            $out[] = [
                'label' => $item['label'],
                'url' => route($item['route']),
                'icon' => $item['icon'],
            ];
        }

        return $out;
    }
}
