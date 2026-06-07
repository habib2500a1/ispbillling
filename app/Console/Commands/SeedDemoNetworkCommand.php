<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Device;
use App\Models\FiberPlantNode;
use App\Models\MikrotikServer;
use App\Models\OltPort;
use App\Models\Package;
use App\Models\PopBox;
use App\Models\Subzone;
use App\Models\Zone;
use App\Services\Olt\OltPortCatalogService;
use App\Support\TenantResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoNetworkCommand extends Command
{
    protected $signature = 'isp:seed-demo-network
                            {--tenant=1 : Tenant id}
                            {--force : Update existing demo_* records}';

    protected $description = 'Seed demo MikroTik router, OLT, POP, PON ports, and sample ONUs for UI/testing';

    public function handle(OltPortCatalogService $portCatalog): int
    {
        $tenantId = max(1, (int) $this->option('tenant'));
        $force = (bool) $this->option('force');

        TenantResolver::fake($tenantId);

        $this->info("Seeding demo network for tenant {$tenantId}…");

        try {
            $result = DB::transaction(function () use ($tenantId, $force, $portCatalog): array {
            $area = $this->ensureArea($tenantId);
            $zone = $this->ensureZone($tenantId, $area->id);
            $subzone = $this->ensureSubzone($tenantId, $zone->id);
            $router = $this->ensureDemoRouter($tenantId, $force);
            $olt = $this->ensureDemoOlt($tenantId, $force);
            $pop = $this->ensureDemoPop($tenantId, $area->id, $force);
            $package = $this->ensureDemoPackage($tenantId, $router->id, $force);
            $ports = $portCatalog->ensureForOlt($olt);
            $onus = $this->ensureDemoOnus($tenantId, $olt, $force);
            $fiber = $this->ensureFiberNodes($tenantId, $pop, $olt, $force);

                return compact('area', 'zone', 'subzone', 'router', 'olt', 'pop', 'package', 'ports', 'onus', 'fiber');
            });
        } finally {
            TenantResolver::clearFake();
        }

        $this->table(
            ['Item', 'Detail'],
            [
                ['Area', $result['area']->name],
                ['Zone', $result['zone']->name],
                ['Sub zone / TJ', $result['subzone']->name],
                ['MikroTik', $result['router']->name.' · '.$result['router']->host],
                ['OLT', $result['olt']->display_name.' · '.$result['olt']->management_ip.' ('.$result['olt']->olt_driver.')'],
                ['POP', $result['pop']->name],
                ['Package', $result['package']->name.' → router #'.$result['package']->mikrotik_server_id],
                ['PON ports', $result['ports']['total'].' total ('.$result['ports']['created'].' new)'],
                ['Demo ONUs', $result['onus'].' seeded'],
                ['Fiber map nodes', $result['fiber'].' nodes'],
            ],
        );

        $this->newLine();
        $this->comment('Admin → MikroTik servers: '.$result['router']->name);
        $this->comment('Admin → OLT hub / OLTs: '.$result['olt']->display_name);
        $this->comment('New subscriber form → Router: '.$result['router']->name.' · TJ box: '.$result['subzone']->name);

        return self::SUCCESS;
    }

    private function ensureArea(int $tenantId): Area
    {
        return Area::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Area'],
            ['is_active' => true],
        );
    }

    private function ensureZone(int $tenantId, int $areaId): Zone
    {
        return Zone::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'area_id' => $areaId, 'name' => 'Demo Zone'],
            ['is_active' => true],
        );
    }

    private function ensureSubzone(int $tenantId, int $zoneId): Subzone
    {
        return Subzone::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'zone_id' => $zoneId, 'name' => 'Demo TJ Box A'],
            ['is_active' => true],
        );
    }

    private function ensureDemoRouter(int $tenantId, bool $force): MikrotikServer
    {
        $attrs = [
            'host' => '10.100.1.1',
            'radius_nas_ip' => '10.100.1.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'legacy_login' => false,
            'api_username' => 'demo',
            'api_password' => 'demo12345',
            'default_ppp_password' => 'demo123',
            'ppp_profile_default' => 'default',
            'is_enabled' => true,
            'last_api_status' => 'demo',
            'last_error' => null,
            'meta' => [
                'demo' => true,
                'note' => 'Lab/demo router — replace host & API password with production MikroTik.',
            ],
        ];

        $router = MikrotikServer::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'demo_router_core')
            ->first();

        if ($router === null) {
            return MikrotikServer::query()->create(array_merge(
                ['tenant_id' => $tenantId, 'name' => 'demo_router_core'],
                $attrs,
            ));
        }

        if ($force) {
            $router->fill($attrs);
            $router->save();
        }

        return $router->fresh();
    }

    private function ensureDemoOlt(int $tenantId, bool $force): Device
    {
        $attrs = [
            'type' => 'olt',
            'display_name' => 'demo_olt_core',
            'management_ip' => '10.100.1.2',
            'snmp_host' => '10.100.1.2',
            'snmp_port' => 161,
            'snmp_community' => 'public',
            'snmp_version' => '2c',
            'olt_driver' => 'bdcom_epon',
            'vendor' => 'bdcom',
            'status' => 'active',
            'location' => 'Demo POP — Core OLT',
            'serial_number' => 'DEMO-OLT-001',
            'meta' => [
                'demo' => true,
                'note' => 'Demo OLT for forms & fiber map — point to real IP for SNMP sync.',
            ],
        ];

        $olt = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where(function ($q): void {
                $q->where('display_name', 'demo_olt_core')
                    ->orWhere('serial_number', 'DEMO-OLT-001');
            })
            ->first();

        if ($olt === null) {
            return Device::query()->create(array_merge(['tenant_id' => $tenantId], $attrs));
        }

        if ($force) {
            $olt->fill($attrs);
            $olt->save();
        }

        return $olt->fresh();
    }

    private function ensureDemoPop(int $tenantId, int $areaId, bool $force): PopBox
    {
        $attrs = [
            'area_id' => $areaId,
            'name' => 'Demo POP Main',
            'address' => 'Demo area, Dhaka',
            'latitude' => 23.8103,
            'longitude' => 90.4125,
            'capacity' => 128,
            'is_active' => true,
            'notes' => 'Demo POP for fiber plant map',
        ];

        $pop = PopBox::query()
            ->where('tenant_id', $tenantId)
            ->where('code', 'DEMO-POP-01')
            ->first();

        if ($pop === null) {
            return PopBox::query()->create(array_merge(
                ['tenant_id' => $tenantId, 'code' => 'DEMO-POP-01'],
                $attrs,
            ));
        }

        if ($force) {
            $pop->fill($attrs);
            $pop->save();
        }

        return $pop->fresh();
    }

    private function ensureDemoPackage(int $tenantId, int $routerId, bool $force): Package
    {
        $attrs = [
            'mikrotik_server_id' => $routerId,
            'mikrotik_profile_name' => 'demo-10m',
            'type' => 'residential',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'show_on_website' => true,
        ];

        $pkg = Package::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Demo 10 Mbps')
            ->first();

        if ($pkg === null) {
            return Package::query()->create(array_merge(
                ['tenant_id' => $tenantId, 'name' => 'Demo 10 Mbps'],
                $attrs,
            ));
        }

        if ($force) {
            $pkg->fill($attrs);
            $pkg->save();
        }

        return $pkg->fresh();
    }

    private function ensureDemoOnus(int $tenantId, Device $olt, bool $force): int
    {
        $samples = [
            ['mac' => '00AD24F0FB01', 'serial' => 'ONU-DEMO-001', 'card' => 0, 'pon' => 1, 'rx' => -22.5, 'tx' => 2.1],
            ['mac' => '00AD24F0FB02', 'serial' => 'ONU-DEMO-002', 'card' => 0, 'pon' => 2, 'rx' => -24.8, 'tx' => 1.9],
        ];

        $count = 0;

        foreach ($samples as $row) {
            $port = OltPort::query()
                ->withoutGlobalScopes()
                ->where('device_id', $olt->id)
                ->where('card_index', $row['card'])
                ->where('pon_index', $row['pon'])
                ->first();

            $existing = Device::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', 'onu')
                ->where('serial_number', $row['serial'])
                ->first();

            if ($existing !== null && ! $force) {
                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'type' => 'onu',
                'connection_type' => 'fiber',
                'display_name' => $row['serial'],
                'serial_number' => $row['serial'],
                'mac_address' => $row['mac'],
                'olt_id' => $olt->id,
                'olt_port_id' => $port?->id,
                'card_no' => $row['card'],
                'pon_no' => $row['pon'],
                'rx_power_dbm' => $row['rx'],
                'tx_power_dbm' => $row['tx'],
                'onu_oper_status' => 'online',
                'status' => 'active',
                'meta' => ['demo' => true, 'epon_port' => 'EPON0/'.$row['pon']],
            ];

            if ($existing === null) {
                Device::query()->create($payload);
            } else {
                $existing->fill($payload);
                $existing->save();
            }

            $count++;
        }

        return $count;
    }

    private function ensureFiberNodes(int $tenantId, PopBox $pop, Device $olt, bool $force): int
    {
        $nodes = [
            [
                'code' => 'DEMO-POP-01',
                'name' => $pop->name,
                'type' => 'pop',
                'latitude' => $pop->latitude,
                'longitude' => $pop->longitude,
                'address' => $pop->address,
                'pop_box_id' => $pop->id,
            ],
            [
                'code' => 'DEMO-OLT-01',
                'name' => $olt->display_name,
                'type' => 'olt',
                'latitude' => 23.8110,
                'longitude' => 90.4130,
                'address' => $olt->location,
                'device_id' => $olt->id,
            ],
        ];

        $created = 0;

        foreach ($nodes as $row) {
            $node = FiberPlantNode::query()
                ->where('tenant_id', $tenantId)
                ->where('code', $row['code'])
                ->first();

            if ($node === null) {
                FiberPlantNode::query()->create(array_merge(['tenant_id' => $tenantId, 'is_active' => true], $row));
                $created++;

                continue;
            }

            if ($force) {
                $node->fill(array_merge($row, ['is_active' => true]));
                $node->save();
                $created++;
            }
        }

        return $created;
    }
}
