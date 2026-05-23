<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Services\Network\GponIntelligenceService;
use App\Services\Network\OltSnmpMonitorService;
use Illuminate\Support\Str;

/**
 * Create OLT devices with driver defaults and optional first SNMP poll.
 */
final class OltProvisioningService
{
    /**
     * @param  array{
     *   display_name: string,
     *   management_ip: string,
     *   snmp_community?: ?string,
     *   olt_driver?: string,
     *   serial_number?: ?string,
     *   location?: ?string,
     *   status?: string
     * }  $input
     * @return array{olt: Device, poll: ?array<string, mixed>}
     */
    public function createQuick(int $tenantId, array $input, bool $pollAfterCreate = true): array
    {
        $driver = (string) ($input['olt_driver'] ?? 'huawei_gpon');
        $ip = trim((string) $input['management_ip']);
        $name = trim((string) $input['display_name']);

        if ($ip === '' || $name === '') {
            throw new \InvalidArgumentException('OLT name and IP address are required.');
        }

        $serial = trim((string) ($input['serial_number'] ?? ''));
        if ($serial === '') {
            $serial = $this->defaultSerial($ip, $name);
        }

        $vendor = config("olt_drivers.drivers.{$driver}.vendor");
        $gponProfile = config("gpon.driver_to_profile.{$driver}") ?? config('gpon.default_profile', 'generic_gpon');

        $olt = Device::query()->create([
            'tenant_id' => $tenantId,
            'type' => 'olt',
            'display_name' => $name,
            'management_ip' => $ip,
            'snmp_community' => filled($input['snmp_community'] ?? null) ? (string) $input['snmp_community'] : null,
            'snmp_version' => 'v2c',
            'snmp_port' => 161,
            'olt_driver' => $driver,
            'vendor' => is_string($vendor) ? $vendor : null,
            'gpon_profile' => is_string($gponProfile) ? $gponProfile : 'generic_gpon',
            'serial_number' => $serial,
            'location' => $input['location'] ?? null,
            'status' => $input['status'] ?? 'active',
        ]);

        $poll = null;
        if ($pollAfterCreate) {
            $poll = app(OltSnmpMonitorService::class)->pollOlt($olt->fresh());
            app(GponIntelligenceService::class)->syncAllOnuOpticalForOlt($olt->fresh());
        }

        return ['olt' => $olt->fresh(), 'poll' => $poll];
    }

    private function defaultSerial(string $ip, string $name): string
    {
        $fromIp = 'OLT-'.str_replace('.', '-', $ip);

        return Str::upper(Str::limit($fromIp, 64, ''));
    }
}
