<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Support\Facades\Log;

/**
 * Pushes PPPoE /ppp/secret rows via RouterOS API (per-tenant MikroTik servers in the panel).
 */
final class MikrotikNetworkProvisioner implements NetworkAccessProvisioner
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
        private readonly MikrotikFleetCoordinator $fleet,
    ) {}

    public function suspendCustomer(Customer $customer, string $reason): void
    {
        foreach ($this->fleet->serversForCustomer($customer) as $server) {
            try {
                $this->mikrotik->upsertPppSecretForCustomer($server, $customer);
                $name = $this->pppSecretName($customer);
                $this->mikrotik->removeActivePppoeSessionsForSecret($server, $name);
                $this->mikrotik->setPppSecretDisabledForCustomer($server, $customer, true);
            } catch (\Throwable $e) {
                Log::channel('single')->error('network.mikrotik.suspend_failed', [
                    'customer_id' => $customer->id,
                    'mikrotik_server_id' => $server->id,
                    'reason' => $reason,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        foreach ($this->fleet->serversForCustomer($customer) as $server) {
            try {
                $this->mikrotik->upsertPppSecretForCustomer($server, $customer);
                $this->mikrotik->setPppSecretDisabledForCustomer($server, $customer, false);
            } catch (\Throwable $e) {
                Log::channel('single')->error('network.mikrotik.unsuspend_failed', [
                    'customer_id' => $customer->id,
                    'mikrotik_server_id' => $server->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncAccessPolicy(Customer $customer): void
    {
        $servers = $this->fleet->serversForCustomer($customer);
        if ($servers->isEmpty()) {
            Log::channel('single')->info('network.mikrotik.no_servers', [
                'customer_id' => $customer->id,
                'tenant_id' => $customer->tenant_id,
            ]);

            return;
        }

        $wantDisabled = ($customer->network_access_state ?? 'active') === 'suspended';
        $name = $this->pppSecretName($customer);

        $serversOk = 0;

        foreach ($servers as $server) {
            try {
                $this->mikrotik->upsertPppSecretForCustomer($server, $customer);
                if ($wantDisabled) {
                    $this->mikrotik->removeActivePppoeSessionsForSecret($server, $name);
                    $this->mikrotik->setPppSecretDisabledForCustomer($server, $customer, true);
                } else {
                    $this->mikrotik->setPppSecretDisabledForCustomer($server, $customer, false);
                }
                $serversOk++;
            } catch (\Throwable $e) {
                Log::channel('single')->error('network.mikrotik.sync_policy_failed', [
                    'customer_id' => $customer->id,
                    'mikrotik_server_id' => $server->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($serversOk > 0) {
            Log::channel('single')->info('network.mikrotik.ppp_sync_applied', [
                'customer_id' => $customer->id,
                'tenant_id' => $customer->tenant_id,
                'servers_ok' => $serversOk,
            ]);
        }
    }

    public function pushOnuRuntimeState(Device $onu): void
    {
        Log::channel('single')->info('network.mikrotik.onu_stub', [
            'device_id' => $onu->id,
            'serial' => $onu->serial_number,
        ]);
    }

    private function pppSecretName(Customer $customer): string
    {
        return $customer->pppLoginName();
    }

}
