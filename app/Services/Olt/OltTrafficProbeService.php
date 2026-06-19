<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltHealthLog;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Schema;

/**
 * Polls IF-MIB HC octets for OLT uplink traffic (Mbps).
 */
final class OltTrafficProbeService
{
    private const IF_HC_IN = '1.3.6.1.2.1.31.1.1.1.6';
    private const IF_HC_OUT = '1.3.6.1.2.1.31.1.1.1.10';

    public function __construct(
        private readonly OltSnmpProbeService $probe,
    ) {}

    /**
     * @return array{download_mbps: ?float, upload_mbps: ?float, in_octets: ?int, out_octets: ?int}
     */
    public function probe(Device $olt): array
    {
        $result = [
            'download_mbps' => null,
            'upload_mbps' => null,
            'in_octets' => null,
            'out_octets' => null,
        ];

        if (($olt->snmp_version ?? 'v2c') !== 'v2c' || ! SnmpClient::available()) {
            return $result;
        }

        try {
            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $inWalk = SnmpClient::walk($peer, $community, self::IF_HC_IN);
            $outWalk = SnmpClient::walk($peer, $community, self::IF_HC_OUT);

            $inTotal = $this->sumOctets($inWalk);
            $outTotal = $this->sumOctets($outWalk);
            $result['in_octets'] = $inTotal;
            $result['out_octets'] = $outTotal;

            $meta = is_array($olt->meta) ? $olt->meta : [];
            $prevIn = (int) ($meta['traffic_in_octets'] ?? 0);
            $prevOut = (int) ($meta['traffic_out_octets'] ?? 0);
            $prevAt = isset($meta['traffic_sampled_at']) ? strtotime((string) $meta['traffic_sampled_at']) : 0;
            $elapsed = $prevAt > 0 ? max(1, time() - $prevAt) : 0;

            if ($elapsed >= 30 && $prevIn > 0 && $inTotal >= $prevIn) {
                $result['download_mbps'] = round((($inTotal - $prevIn) * 8) / $elapsed / 1_000_000, 2);
                $result['upload_mbps'] = round((($outTotal - $prevOut) * 8) / $elapsed / 1_000_000, 2);
            }

            $meta['traffic_in_octets'] = $inTotal;
            $meta['traffic_out_octets'] = $outTotal;
            $meta['traffic_sampled_at'] = now()->toIso8601String();
            $meta['traffic_download_mbps'] = $result['download_mbps'];
            $meta['traffic_upload_mbps'] = $result['upload_mbps'];
            $olt->forceFill(['meta' => $meta])->saveQuietly();
        } catch (\Throwable) {
            // SNMP optional
        }

        return $result;
    }

    public function persistToHealthLog(Device $olt, array $traffic): void
    {
        if (! Schema::hasTable('olt_health_logs')) {
            return;
        }

        OltHealthLog::query()->create([
            'tenant_id' => $olt->tenant_id,
            'device_id' => $olt->id,
            'snmp_ok' => true,
            'metrics' => [
                'download_mbps' => $traffic['download_mbps'] ?? null,
                'upload_mbps' => $traffic['upload_mbps'] ?? null,
                'in_octets' => $traffic['in_octets'] ?? null,
                'out_octets' => $traffic['out_octets'] ?? null,
            ],
            'sampled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $walk
     */
    private function sumOctets(array $walk): int
    {
        $total = 0;
        foreach ($walk as $value) {
            $n = SnmpClient::parseCounter64($value);
            if ($n !== null) {
                $total += $n;
            }
        }

        return $total;
    }
}
