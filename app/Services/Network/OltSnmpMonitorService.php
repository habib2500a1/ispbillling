<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\SnmpPollLog;
use App\Services\Olt\OltHealthProbeService;
use App\Services\Olt\OltSnmpProbeService;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

class OltSnmpMonitorService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly GponIntelligenceService $gpon,
        private readonly BdcomEponOnuSyncService $bdcomEpon,
        private readonly OltHealthProbeService $healthProbe,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pollOlt(Device $olt): array
    {
        $profile = $this->gpon->resolveProfile($olt);
        $oids = $this->gpon->oidsForProfile($profile);

        $result = [
            'success' => false,
            'profile' => $profile,
            'sys_descr' => null,
            'sys_uptime_ticks' => null,
            'interfaces_total' => 0,
            'interfaces_up' => 0,
            'onus_online' => 0,
            'onus_offline' => 0,
            'pon_ports' => $olt->ports()->count(),
            'error' => null,
        ];

        $onus = $olt->onus()->get(['onu_oper_status']);
        $result['onus_online'] = $onus->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();
        $result['onus_offline'] = $onus->count() - $result['onus_online'];

        try {
            if (($olt->snmp_version ?? 'v2c') !== 'v2c') {
                throw new \RuntimeException('Automated SNMP poll supports v2c only.');
            }

            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);

            $result['sys_descr'] = SnmpClient::get($peer, $community, $oids['sys_descr'] ?? '1.3.6.1.2.1.1.1.0');
            if ($result['sys_descr'] === null) {
                throw new \RuntimeException('SNMP unreachable (sysDescr).');
            }

            $uptimeRaw = SnmpClient::get($peer, $community, $oids['sys_uptime'] ?? '1.3.6.1.2.1.1.3.0');
            $result['sys_uptime_ticks'] = SnmpClient::parseTimeticks($uptimeRaw);

            $operWalk = SnmpClient::walk($peer, $community, $oids['if_oper_status'] ?? '1.3.6.1.2.1.2.2.1.8');
            $result['interfaces_total'] = count($operWalk);
            $result['interfaces_up'] = collect($operWalk)->filter(function (string $v): bool {
                $v = strtolower(trim($v));

                return $v === '1' || str_contains($v, 'up');
            })->count();

            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('olt_snmp_poll.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        if ($result['success']
            && ! config('sync.skip_bdcom_in_olt_poll', true)
            && $this->bdcomEpon->supportsDriver($olt)) {
            $bdcom = $this->bdcomEpon->syncOlt($olt->fresh(), false);
            $result['bdcom_onu_discovered'] = $bdcom['discovered'];
            $result['bdcom_onu_created'] = $bdcom['created'];
            $result['bdcom_onu_updated'] = $bdcom['updated'];
            if ($bdcom['error']) {
                $result['bdcom_sync_error'] = $bdcom['error'];
            }
            $onus = $olt->fresh()->onus()->get(['onu_oper_status']);
            $result['onus_online'] = $onus->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();
            $result['onus_offline'] = $onus->count() - $result['onus_online'];
        }

        $healthContext = [
            'sys_uptime_ticks' => $result['sys_uptime_ticks'],
            'interfaces_up' => $result['interfaces_up'],
            'interfaces_total' => $result['interfaces_total'],
            'onus_online' => $result['onus_online'],
            'onus_offline' => $result['onus_offline'],
            'pon_ports' => $result['pon_ports'],
        ];

        if (config('network.olt_health_poll_enabled', true)) {
            $this->healthProbe->probeAndPersist($olt, $healthContext);
            $olt->refresh();
        }

        $health = array_merge(is_array($olt->olt_health) ? $olt->olt_health : [], [
            'last_poll' => now()->toIso8601String(),
            'gpon_profile' => $profile,
            'sys_uptime_ticks' => $result['sys_uptime_ticks'],
            'interfaces_up' => $result['interfaces_up'],
            'interfaces_total' => $result['interfaces_total'],
            'onus_online' => $result['onus_online'],
            'onus_offline' => $result['onus_offline'],
            'snmp_ok' => $result['success'],
        ]);

        $olt->forceFill([
            'gpon_profile' => $profile,
            'last_snmp_poll_at' => now(),
            'last_health_polled_at' => now(),
            'last_polled_at' => now(),
            'olt_health' => $health,
            'status' => $result['success'] ? ($olt->status === 'offline' ? 'active' : $olt->status) : 'offline',
        ])->save();

        SnmpPollLog::query()->create([
            'tenant_id' => $olt->tenant_id,
            'device_id' => $olt->id,
            'poll_type' => 'olt',
            'success' => $result['success'],
            'gpon_profile' => $profile,
            'sys_uptime_ticks' => $result['sys_uptime_ticks'],
            'interfaces_total' => $result['interfaces_total'],
            'interfaces_up' => $result['interfaces_up'],
            'onus_online' => $result['onus_online'],
            'onus_offline' => $result['onus_offline'],
            'pon_ports' => $result['pon_ports'],
            'error_message' => $result['error'],
            'metrics' => [
                'sys_descr' => $result['sys_descr'] ? substr((string) $result['sys_descr'], 0, 500) : null,
            ],
            'polled_at' => now(),
        ]);

        return $result;
    }
}
