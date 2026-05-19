<?php

namespace App\Services\Bandwidth;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\BandwidthAbuseAlert;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

final class AbuseEnforcementService
{
    public function __construct(
        private readonly NetworkAccessProvisioner $provisioner,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('bandwidth.abuse_auto_enforce_enabled', false);
    }

    public function applyFromAlert(BandwidthAbuseAlert $alert): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $customer = $alert->customer;
        if ($customer === null) {
            return;
        }

        $action = (string) ($alert->meta['action'] ?? '');
        if ($action === 'suspend') {
            $this->suspendCustomer($customer, 'abuse_'.$alert->alert_type);

            return;
        }

        if ($alert->alert_type === BandwidthAbuseAlert::TYPE_EXCESSIVE_DAILY
            && (bool) config('bandwidth.abuse_suspend_on_daily_quota', false)) {
            $this->suspendCustomer($customer, 'daily_quota_exceeded');
        }
    }

    private function suspendCustomer(Customer $customer, string $reason): void
    {
        try {
            $this->provisioner->suspendCustomer($customer, $reason);
            $customer->forceFill(['network_access_state' => 'suspended'])->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('abuse.enforce.suspend_failed', [
                'customer_id' => $customer->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
