<?php

namespace App\Services\Network;

use App\Models\BandwidthAbuseAlert;
use App\Models\Customer;
use App\Services\Bandwidth\AbuseEnforcementService;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

final class NetflowAbuseEvaluator
{
    public function __construct(
        private readonly NetflowAnalysisService $analysis,
        private readonly AbuseEnforcementService $enforcement,
    ) {}

    public function evaluateTenant(?int $tenantId = null): int
    {
        if (! config('netflow.enabled', false) || ! config('netflow.abuse_eval_enabled', true)) {
            return 0;
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $hours = (int) config('netflow.abuse_window_hours', 24);
        $thresholdGb = (float) config('netflow.abuse_threshold_gb', 200);
        if ($thresholdGb <= 0) {
            return 0;
        }

        $thresholdBytes = (int) ($thresholdGb * 1073741824);
        $created = 0;

        foreach ($this->analysis->subscriberUsage($tenantId, $hours, 500) as $row) {
            $bytes = (int) ($row['bytes'] ?? 0);
            if ($bytes < $thresholdBytes) {
                continue;
            }

            $customerId = (int) ($row['customer_id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $customer = Customer::withoutGlobalScopes()->find($customerId);
            if ($customer === null) {
                continue;
            }

            if ($this->raiseNetflowAlert($customer, $bytes, $thresholdGb, $hours)) {
                $created++;
            }
        }

        return $created;
    }

    private function raiseNetflowAlert(Customer $customer, int $bytes, float $thresholdGb, int $hours): bool
    {
        $exists = BandwidthAbuseAlert::query()
            ->where('customer_id', $customer->id)
            ->where('alert_type', BandwidthAbuseAlert::TYPE_NETFLOW_HIGH)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', now()->subHours($hours))
            ->exists();

        if ($exists) {
            return false;
        }

        $alert = BandwidthAbuseAlert::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'alert_type' => BandwidthAbuseAlert::TYPE_NETFLOW_HIGH,
            'severity' => 'danger',
            'message' => sprintf(
                'NetFlow usage %.2f GB in %dh exceeds threshold %.0f GB.',
                $bytes / 1073741824,
                $hours,
                $thresholdGb,
            ),
            'meta' => [
                'bytes' => $bytes,
                'threshold_gb' => $thresholdGb,
                'action' => config('netflow.abuse_action', 'alert'),
            ],
        ]);

        if ((string) config('netflow.abuse_action') === 'suspend') {
            $alert->forceFill(['meta' => array_merge($alert->meta ?? [], ['action' => 'suspend'])])->saveQuietly();
            $this->enforcement->applyFromAlert($alert->fresh());
        }

        return true;
    }
}
