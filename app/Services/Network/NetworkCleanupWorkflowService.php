<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\Device;
use App\Models\NetworkCleanupLog;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Mikrotik\MikrotikServerService;
use App\Services\Radius\RadiusUserManagementService;
use App\Support\CustomerStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Safe network cleanup — never deletes MAC on simple ONU offline.
 * Suspended: 24h+ offline → optional radius/PPP cleanup only.
 * Terminated: 30d+ offline → full PPP/radius teardown + ONU unlink.
 */
final class NetworkCleanupWorkflowService
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
        private readonly MikrotikFleetCoordinator $fleet,
        private readonly RadiusUserManagementService $radius,
    ) {}

    /**
     * @return array{suspended: int, terminated: int, skipped_active: int}
     */
    public function runForTenant(int $tenantId): array
    {
        if (! config('network_cleanup.enabled', true)) {
            return ['suspended' => 0, 'terminated' => 0, 'skipped_active' => 0];
        }

        $stats = ['suspended' => 0, 'terminated' => 0, 'skipped_active' => 0];

        if (config('network_cleanup.suspended.enabled', true)) {
            $stats['suspended'] = $this->processSuspendedCustomers($tenantId);
        }

        if (config('network_cleanup.terminated.enabled', true)) {
            $stats['terminated'] = $this->processTerminatedCustomers($tenantId);
        }

        return $stats;
    }

    private function processSuspendedCustomers(int $tenantId): int
    {
        $hours = max(1, (int) config('network_cleanup.suspended.offline_grace_hours', 24));
        $processed = 0;

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::SUSPENDED)
            ->where(function ($q): void {
                $q->where('network_access_state', 'suspended')
                    ->orWhere('status', CustomerStatus::SUSPENDED);
            })
            ->whereHas('devices', fn ($q) => $q->where('type', 'onu'))
            ->chunkById(100, function ($customers) use ($hours, &$processed): void {
                foreach ($customers as $customer) {
                    if (! $this->customerOnuOfflineLongEnough($customer, $hours)) {
                        continue;
                    }

                    if ($this->isActiveBillingCustomer($customer)) {
                        continue;
                    }

                    $this->cleanupSuspendedNetwork($customer);
                    $processed++;
                }
            });

        return $processed;
    }

    private function processTerminatedCustomers(int $tenantId): int
    {
        $days = max(1, (int) config('network_cleanup.terminated.offline_grace_days', 30));
        $processed = 0;

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::TERMINATED)
            ->chunkById(100, function ($customers) use ($days, &$processed): void {
                foreach ($customers as $customer) {
                    if (! $this->customerOfflineLongEnough($customer, $days)) {
                        continue;
                    }

                    $this->cleanupTerminatedNetwork($customer);
                    $processed++;
                }
            });

        return $processed;
    }

    private function cleanupSuspendedNetwork(Customer $customer): void
    {
        $login = $customer->pppLoginName();
        $actions = [];

        if (config('network_cleanup.suspended.kick_ppp_sessions', true)) {
            $actions['sessions_kicked'] = $this->mikrotik->kickPppoeActiveSessionsForCustomer($customer);
        }

        if (config('network_cleanup.suspended.ensure_ppp_disabled', true)) {
            foreach ($this->fleet->serversForCustomer($customer) as $server) {
                try {
                    $this->mikrotik->setPppSecretDisabledForCustomer($server, $customer, true);
                    $actions['ppp_disabled'] = true;
                } catch (\Throwable $e) {
                    Log::warning('network_cleanup.suspended_ppp_failed', [
                        'customer_id' => $customer->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (config('network_cleanup.suspended.remove_radius_user', false) && $this->radius->isAvailable()) {
            try {
                $this->radius->deleteUser($login);
                $actions['radius_removed'] = true;
            } catch (\Throwable $e) {
                Log::warning('network_cleanup.suspended_radius_failed', [
                    'customer_id' => $customer->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->logCleanup($customer, 'suspended_offline_cleanup', $actions);
    }

    private function cleanupTerminatedNetwork(Customer $customer): void
    {
        $login = $customer->pppLoginName();
        $actions = [];

        if (config('network_cleanup.terminated.kick_ppp_sessions', true)) {
            $actions['sessions_kicked'] = $this->mikrotik->kickPppoeActiveSessionsForCustomer($customer);
        }

        if (config('network_cleanup.terminated.remove_ppp_secret', true)) {
            foreach ($this->fleet->serversForCustomer($customer) as $server) {
                try {
                    $removed = $this->mikrotik->removePppSecret($server, $login);
                    if ($removed) {
                        $actions['ppp_secret_removed'] = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('network_cleanup.terminated_ppp_failed', [
                        'customer_id' => $customer->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (config('network_cleanup.terminated.remove_radius_user', true) && $this->radius->isAvailable()) {
            try {
                $this->radius->deleteUser($login);
                $actions['radius_removed'] = true;
            } catch (\Throwable $e) {
                Log::warning('network_cleanup.terminated_radius_failed', [
                    'customer_id' => $customer->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (config('network_cleanup.terminated.unlink_onu', true)) {
            $unlinked = Device::query()
                ->where('customer_id', $customer->id)
                ->where('type', 'onu')
                ->update(['customer_id' => null]);
            $actions['onu_unlinked'] = $unlinked;
        }

        $this->logCleanup($customer, 'terminated_full_cleanup', $actions);
    }

    private function customerOnuOfflineLongEnough(Customer $customer, int $hours): bool
    {
        $onu = $customer->devices()->where('type', 'onu')->first();
        if ($onu === null) {
            return $this->customerPppOfflineLongEnough($customer, $hours);
        }

        if (in_array(strtolower((string) $onu->onu_oper_status), ['online', 'active', 'up'], true)) {
            return false;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $offlineSince = $meta['onu_offline_since'] ?? null;
        if ($offlineSince === null) {
            return false;
        }

        return Carbon::parse($offlineSince)->diffInHours(now()) >= $hours;
    }

    private function customerOfflineLongEnough(Customer $customer, int $days): bool
    {
        if ($customer->is_ppp_online) {
            return false;
        }

        $since = $customer->ppp_last_seen_at;
        if ($since === null) {
            $onu = $customer->devices()->where('type', 'onu')->first();
            $meta = is_array($onu?->meta) ? $onu->meta : [];
            $sinceRaw = $meta['onu_offline_since'] ?? null;
            $since = $sinceRaw ? Carbon::parse($sinceRaw) : null;
        }

        if ($since === null) {
            return false;
        }

        return $since->diffInDays(now()) >= $days;
    }

    private function customerPppOfflineLongEnough(Customer $customer, int $hours): bool
    {
        if ($customer->is_ppp_online) {
            return false;
        }

        $since = $customer->ppp_last_seen_at;
        if ($since === null) {
            return false;
        }

        return $since->diffInHours(now()) >= $hours;
    }

    private function isActiveBillingCustomer(Customer $customer): bool
    {
        return ! in_array($customer->status, [CustomerStatus::SUSPENDED, CustomerStatus::TERMINATED], true)
            && ($customer->network_access_state ?? 'active') === 'active';
    }

    /**
     * @param  array<string, mixed>  $actions
     */
    private function logCleanup(Customer $customer, string $workflow, array $actions): void
    {
        if (! config('network_cleanup.log_actions', true)) {
            return;
        }

        try {
            NetworkCleanupLog::query()->create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'workflow' => $workflow,
                'actions' => $actions,
                'processed_at' => now(),
            ]);
        } catch (\Throwable) {
            Log::info('network_cleanup.completed', [
                'customer_id' => $customer->id,
                'workflow' => $workflow,
                'actions' => $actions,
            ]);
        }
    }
}
