<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Models\NetflowFlow;
use App\Models\SnmpPollLog;
use App\Support\SnmpClient;
use Filament\Pages\Page;

class NetworkIntelligenceHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.pages.network-intelligence-hub';

    protected static ?string $navigationLabel = 'Network center';

    protected static ?string $title = 'Network intelligence';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 0;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $olts = Device::query()->olts()->count();
        $onus = Device::query()->where('type', 'onu')->count();
        $onusOnline = Device::query()->where('type', 'onu')->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();
        $lastPoll = SnmpPollLog::query()->orderByDesc('polled_at')->first();
        $flows24h = NetflowFlow::query()->where('sampled_at', '>=', now()->subDay())->count();

        return [
            'olts' => $olts,
            'onus' => $onus,
            'onus_online' => $onusOnline,
            'onus_offline' => max(0, $onus - $onusOnline),
            'snmp_available' => SnmpClient::available(),
            'last_poll' => $lastPoll?->polled_at?->diffForHumans(),
            'last_poll_ok' => $lastPoll?->success,
            'flows_24h' => $flows24h,
            'netflow_enabled' => (bool) config('netflow.enabled'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
