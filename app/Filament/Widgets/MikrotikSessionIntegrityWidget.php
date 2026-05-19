<?php

namespace App\Filament\Widgets;

use App\Models\MikrotikSessionAlert;
use App\Services\Mikrotik\MikrotikSessionAlertService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class MikrotikSessionIntegrityWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.mikrotik-session-integrity';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'isp-engineer']) ?? false;
    }

    public function suspendAlert(int $alertId): void
    {
        $alert = MikrotikSessionAlert::query()->with('customer')->findOrFail($alertId);
        app(MikrotikSessionAlertService::class)->suspendFromAlert($alert);

        Notification::make()->title('Subscriber suspended')->success()->send();
    }

    public function resolveAlert(int $alertId): void
    {
        $alert = MikrotikSessionAlert::query()->findOrFail($alertId);
        app(MikrotikSessionAlertService::class)->resolve($alert);

        Notification::make()->title('Alert resolved')->success()->send();
    }

    /**
     * @return array{open: int, critical: int, items: \Illuminate\Support\Collection<int, MikrotikSessionAlert>}
     */
    protected function getViewData(): array
    {
        $open = MikrotikSessionAlert::query()->whereNull('resolved_at');
        $critical = (clone $open)->where('severity', 'critical')->count();

        return [
            'open' => (clone $open)->count(),
            'critical' => $critical,
            'items' => (clone $open)->with('customer:id,name,customer_code')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(),
        ];
    }
}
