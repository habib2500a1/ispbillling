<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SubscriberLifecycleWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    protected static string $view = 'filament.widgets.subscriber-lifecycle';

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    public function getCounts(): array
    {
        $tenantId = TenantResolver::currentTenantId() ?? 0;
        $today = now()->toDateString();

        $counts = Cache::remember(
            "subscriber_lifecycle:{$tenantId}:{$today}",
            60,
            function () use ($tenantId): array {
                $base = Customer::query();
                $bandwidth = app(BandwidthCollectionService::class);

                return [
                    'online' => $bandwidth->displayedOnlineCount($tenantId, $base),
                    'active' => (clone $base)->where('status', CustomerStatus::ACTIVE)->count(),
                    'free' => (clone $base)->where('subscriber_type', SubscriberType::FREE)
                        ->where('status', '!=', CustomerStatus::TERMINATED)->count(),
                    'vip' => (clone $base)->where('subscriber_type', SubscriberType::VIP)
                        ->where('status', '!=', CustomerStatus::TERMINATED)->count(),
                    'expired' => (clone $base)->where('status', '!=', CustomerStatus::TERMINATED)
                        ->where(function ($q): void {
                            $q->where('status', CustomerStatus::EXPIRED)
                                ->orWhere(fn ($q2) => $q2->whereNotNull('service_expires_at')
                                    ->whereDate('service_expires_at', '<', now()->toDateString()));
                        })->count(),
                    'suspended' => (clone $base)->where('status', CustomerStatus::SUSPENDED)->count(),
                    'left' => (clone $base)->where('status', CustomerStatus::TERMINATED)->count(),
                    'expiring_soon' => (clone $base)->whereNotNull('service_expires_at')
                        ->whereDate('service_expires_at', '>=', now()->toDateString())
                        ->whereDate('service_expires_at', '<=', now()->addDays(7)->toDateString())
                        ->where('status', '!=', CustomerStatus::TERMINATED)->count(),
                ];
            },
        );

        return array_merge($counts, [
            'urls' => [
                'free' => CustomerResource::getUrl('free'),
                'vip' => CustomerResource::getUrl('vip'),
                'expired' => CustomerResource::getUrl('expired'),
                'suspended' => CustomerResource::getUrl('suspended'),
                'left' => CustomerResource::getUrl('left'),
                'all' => CustomerResource::getUrl('index'),
                'active' => CustomerResource::getUrl('active'),
                'today' => CustomerResource::getUrl('today'),
                'expire_3' => CustomerResource::getUrl('expire-3'),
                'expire_7' => CustomerResource::getUrl('expire-7'),
                'pending' => CustomerResource::getUrl('pending'),
            ],
        ]);
    }
}
