<?php

namespace App\Services\IspOs;

use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\MikrotikServer;
use App\Support\TenantResolver;

/**
 * Rules-based root cause — insights only, no autonomous actions.
 */
final class RootCauseAnalysisService
{
    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    public function analyze(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $results = [];

        foreach ($this->analyzeFiberFaults($tenantId) as $item) {
            $results[] = $item;
        }
        foreach ($this->analyzeOfflineOlts($tenantId) as $item) {
            $results[] = $item;
        }
        foreach ($this->analyzeOfflineRouters($tenantId) as $item) {
            $results[] = $item;
        }

        return array_slice($results, 0, 10);
    }

    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    private function analyzeFiberFaults(int $tenantId): array
    {
        return FiberFaultLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->with('olt:id,display_name,label')
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->map(function (FiberFaultLog $f): array {
                $fraction = ($f->affected_onu_count ?? 0) > 0;
                $cause = match ($f->fault_type) {
                    'mass_offline' => 'probable_fiber_cut',
                    default => 'signal_failure',
                };

                return [
                    'root_cause' => $cause,
                    'confidence' => $fraction ? 0.87 : 0.65,
                    'message' => sprintf(
                        'Probable %s on %s — %d ONUs affected.',
                        str_replace('_', ' ', $cause),
                        $f->olt?->display_name ?? $f->olt?->label ?? 'OLT',
                        (int) $f->affected_onu_count,
                    ),
                    'tone' => 'critical',
                ];
            })
            ->all();
    }

    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    private function analyzeOfflineOlts(int $tenantId): array
    {
        return Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', 'offline')
            ->limit(5)
            ->get()
            ->map(fn (Device $olt): array => [
                'root_cause' => 'olt_offline',
                'confidence' => 0.92,
                'message' => 'OLT offline — '.($olt->display_name ?? $olt->label ?? 'OLT').' — check power and uplink.',
                'tone' => 'critical',
            ])
            ->all();
    }

    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    private function analyzeOfflineRouters(int $tenantId): array
    {
        return MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('last_api_status', 'offline')
            ->limit(5)
            ->get()
            ->map(fn (MikrotikServer $r): array => [
                'root_cause' => 'router_offline',
                'confidence' => 0.90,
                'message' => 'Router offline — '.$r->name.' — uplink or power failure likely.',
                'tone' => 'critical',
            ])
            ->all();
    }
}
