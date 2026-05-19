<?php

namespace App\Services\Bandwidth;

use App\Models\BandwidthAbuseAlert;
use App\Models\BandwidthSample;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Device;
use App\Models\PppSessionLog;

final class AbuseDetectionService
{
    public function evaluateTenant(int $tenantId): int
    {
        $created = 0;

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'suspended'])
            ->with('package')
            ->cursor();

        foreach ($customers as $customer) {
            $created += $this->evaluateCustomer($customer);
        }

        return $created;
    }

    public function evaluateCustomer(Customer $customer): int
    {
        $created = 0;
        $created += $this->checkConcurrentSessions($customer);
        $created += $this->checkDailyQuota($customer);
        $created += $this->checkSpeedBurst($customer);
        $created += $this->checkMacBinding($customer);

        return $created;
    }

    private function checkConcurrentSessions(Customer $customer): int
    {
        $max = (int) config('bandwidth.max_concurrent_sessions', 1);
        $active = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();

        if ($active <= $max) {
            return 0;
        }

        return $this->raiseAlert(
            $customer,
            BandwidthAbuseAlert::TYPE_CONCURRENT_SESSIONS,
            'warning',
            sprintf('Subscriber has %d active PPP sessions (max %d).', $active, $max),
            ['active_sessions' => $active, 'max' => $max],
        ) ? 1 : 0;
    }

    private function checkDailyQuota(Customer $customer): int
    {
        $package = $customer->package;
        if ($package === null || $package->included_data_gb === null || (float) $package->included_data_gb <= 0) {
            return 0;
        }

        $daily = BandwidthUsageDaily::query()
            ->where('customer_id', $customer->id)
            ->whereDate('usage_date', today())
            ->first();

        if ($daily === null) {
            return 0;
        }

        $usedBytes = (int) $daily->bytes_in + (int) $daily->bytes_out;
        $quotaBytes = (float) $package->included_data_gb * 1073741824;
        $threshold = $quotaBytes * (float) config('bandwidth.daily_quota_multiplier', 1.2);

        if ($usedBytes < $threshold) {
            return 0;
        }

        return $this->raiseAlert(
            $customer,
            BandwidthAbuseAlert::TYPE_EXCESSIVE_DAILY,
            'warning',
            sprintf(
                'Daily usage %.2f GB exceeds package quota %.2f GB (threshold %.0f%%).',
                $usedBytes / 1073741824,
                (float) $package->included_data_gb,
                config('bandwidth.daily_quota_multiplier', 1.2) * 100
            ),
            ['bytes_used' => $usedBytes, 'quota_bytes' => $quotaBytes],
        ) ? 1 : 0;
    }

    private function checkSpeedBurst(Customer $customer): int
    {
        $package = $customer->package;
        if ($package === null || (int) $package->download_mbps <= 0) {
            return 0;
        }

        $capBps = (int) $package->download_mbps * 1000000;
        $threshold = $capBps * (float) config('bandwidth.speed_burst_multiplier', 1.15);
        $needed = (int) config('bandwidth.speed_burst_sample_count', 3);

        $recent = BandwidthSample::query()
            ->where('customer_id', $customer->id)
            ->where('sampled_at', '>=', now()->subMinutes(30))
            ->orderByDesc('sampled_at')
            ->limit($needed)
            ->get();

        if ($recent->count() < $needed) {
            return 0;
        }

        foreach ($recent as $sample) {
            if ((int) ($sample->rate_in_bps ?? 0) < $threshold) {
                return 0;
            }
        }

        $peak = $recent->max('rate_in_bps');

        return $this->raiseAlert(
            $customer,
            BandwidthAbuseAlert::TYPE_SPEED_BURST,
            'danger',
            sprintf(
                'Download rate ~%s Mbps exceeds package %d Mbps cap.',
                round(((int) $peak) / 1000000, 1),
                (int) $package->download_mbps
            ),
            ['peak_rate_in_bps' => $peak, 'cap_bps' => $capBps],
        ) ? 1 : 0;
    }

    private function checkMacBinding(Customer $customer): int
    {
        $strictDevices = Device::query()
            ->where('customer_id', $customer->id)
            ->where('mac_binding_strict', true)
            ->whereNotNull('mac_address')
            ->get();

        if ($strictDevices->isEmpty()) {
            return 0;
        }

        $allowed = $strictDevices->map(fn (Device $d) => strtolower(preg_replace('/[^a-f0-9]/', '', (string) $d->mac_address) ?? ''))->filter()->all();

        $sessions = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereNotNull('caller_id')
            ->get();

        foreach ($sessions as $session) {
            $mac = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $session->caller_id) ?? '');
            if ($mac !== '' && ! in_array($mac, $allowed, true)) {
                return $this->raiseAlert(
                    $customer,
                    BandwidthAbuseAlert::TYPE_MAC_MISMATCH,
                    'danger',
                    'PPP session MAC does not match any strict-bound device for this subscriber.',
                    ['session_mac' => $session->caller_id, 'allowed' => $allowed],
                ) ? 1 : 0;
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function raiseAlert(Customer $customer, string $type, string $severity, string $message, array $meta): bool
    {
        $exists = BandwidthAbuseAlert::query()
            ->where('customer_id', $customer->id)
            ->where('alert_type', $type)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        if ($exists) {
            return false;
        }

        BandwidthAbuseAlert::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'alert_type' => $type,
            'severity' => $severity,
            'message' => $message,
            'meta' => $meta,
        ]);

        return true;
    }
}
