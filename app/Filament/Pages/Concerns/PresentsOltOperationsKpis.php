<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Resources\OltResource;
use App\Models\Device;

/**
 * UI-only KPI presenters for OLT operations center (reads existing models).
 */
trait PresentsOltOperationsKpis
{
    /**
     * @return list<array{label: string, value: string, hint?: string, tone: string, url?: string}>
     */
    public function getOltOperationsKpis(): array
    {
        $s = method_exists($this, 'getStats') ? $this->getStats() : [];
        $olts = (int) ($s['olts'] ?? 0);
        $oltsActive = (int) ($s['olts_active'] ?? $olts);
        $oltsOffline = max(0, $olts - $oltsActive);
        $opticalUrl = OpticalMonitoringHub::getUrl();
        $oltUrl = OltResource::getUrl();

        return [
            [
                'label' => 'Total OLTs',
                'value' => number_format($olts),
                'hint' => number_format($oltsActive).' online',
                'tone' => 'cyan',
                'url' => $oltUrl,
            ],
            [
                'label' => 'Online OLTs',
                'value' => number_format($oltsActive),
                'tone' => 'emerald',
                'url' => $oltUrl,
            ],
            [
                'label' => 'Offline OLTs',
                'value' => number_format($oltsOffline),
                'tone' => 'rose',
                'url' => $oltUrl,
            ],
            [
                'label' => 'PON ports',
                'value' => number_format($s['pon_ports'] ?? 0),
                'tone' => 'indigo',
                'url' => $opticalUrl.'?tab=pon',
            ],
            [
                'label' => 'Active ONUs',
                'value' => number_format($s['onus_online'] ?? 0),
                'hint' => number_format($s['onus'] ?? 0).' total',
                'tone' => 'violet',
                'url' => $opticalUrl,
            ],
            [
                'label' => 'Offline ONUs',
                'value' => number_format($s['onus_offline'] ?? 0),
                'tone' => 'slate',
                'url' => $opticalUrl,
            ],
            [
                'label' => 'Unauthorized ONUs',
                'value' => number_format($this->getUnauthorizedOnuCount()),
                'hint' => 'Unlinked or auth failed',
                'tone' => 'amber',
                'url' => $opticalUrl,
            ],
            [
                'label' => 'Signal warnings',
                'value' => number_format($s['warning_onus'] ?? 0),
                'tone' => 'amber',
                'url' => $opticalUrl.'?tab=charts',
            ],
            [
                'label' => 'Critical alerts',
                'value' => number_format(($s['critical_onus'] ?? 0) + ($s['active_alarms'] ?? 0)),
                'hint' => number_format($s['fiber_faults'] ?? 0).' fiber faults',
                'tone' => 'rose',
                'url' => $opticalUrl.'?tab=alerts',
            ],
        ];
    }

    protected function getUnauthorizedOnuCount(): int
    {
        try {
            $tenantId = \App\Support\TenantResolver::requiredTenantId();

            return (int) Device::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', 'onu')
                ->where(function ($q): void {
                    $q->whereNull('customer_id')
                        ->orWhere('onu_oper_status', 'auth_fail');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
