<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\EnablesLivePolling;
use App\Models\BandwidthAbuseAlert;
use App\Models\BandwidthUsageDaily;
use App\Models\PppSessionLog;
use App\Support\TenantResolver;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class BandwidthMonitorStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    use EnablesLivePolling;

    protected static ?int $sort = 0;

    #[On('bandwidth-refresh')]
    public function refreshStats(): void
    {
        // Triggers widget re-render.
    }

    protected function getStats(): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        $online = PppSessionLog::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $todayUsage = BandwidthUsageDaily::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('usage_date', today())
            ->selectRaw('COALESCE(SUM(bytes_in + bytes_out), 0) as total')
            ->value('total');

        $monthUsage = BandwidthUsageDaily::query()
            ->where('tenant_id', $tenantId)
            ->where('usage_date', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(bytes_in + bytes_out), 0) as total')
            ->value('total');

        $peakToday = BandwidthUsageDaily::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('usage_date', today())
            ->max('peak_rate_in_bps');

        $alerts = BandwidthAbuseAlert::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->count();

        return [
            Stat::make('Online now', number_format($online))
                ->description('Active PPP sessions')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Usage today', BandwidthUsageDaily::formatBytes((int) $todayUsage))
                ->description('Month: '.BandwidthUsageDaily::formatBytes((int) $monthUsage))
                ->icon('heroicon-o-arrow-down-tray'),
            Stat::make('Peak download today', BandwidthUsageDaily::formatBps((int) $peakToday))
                ->description('Highest rate seen')
                ->icon('heroicon-o-bolt'),
            Stat::make('Open abuse alerts', number_format($alerts))
                ->color($alerts > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-exclamation'),
        ];
    }
}
