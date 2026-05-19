<?php

namespace App\Services\Network;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\MikrotikServerResource;
use App\Filament\Resources\OltResource;
use App\Models\Area;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use Illuminate\Support\Collection;

class NetworkTopologyService
{
    private const int ONU_PREVIEW_LIMIT = 12;

    /**
     * @return array{
     *     summary: array<string, int|float>,
     *     mikrotik: list<array<string, mixed>>,
     *     olts: list<array<string, mixed>>,
     *     geo: list<array<string, mixed>>,
     * }
     */
    public function build(): array
    {
        $onlineStatuses = ['online', 'active', 'up'];

        $mikrotik = MikrotikServer::query()
            ->orderBy('name')
            ->get()
            ->map(function (MikrotikServer $server): array {
                $customers = Customer::query()->where('mikrotik_server_id', $server->id)->count();
                $online = Customer::query()
                    ->where('mikrotik_server_id', $server->id)
                    ->where('is_ppp_online', true)
                    ->count();

                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'host' => $server->host,
                    'enabled' => (bool) $server->is_enabled,
                    'api_ok' => $server->last_api_status === 'ok',
                    'customers' => $customers,
                    'online' => $online,
                    'edit_url' => MikrotikServerResource::getUrl('edit', ['record' => $server]),
                ];
            })
            ->values()
            ->all();

        $olts = Device::query()
            ->olts()
            ->with([
                'ports' => fn ($q) => $q->orderBy('card_index')->orderBy('pon_index'),
                'ports.onus' => fn ($q) => $q->with('customer:id,name,customer_code,status')->orderBy('serial_number'),
            ])
            ->orderBy('display_name')
            ->orderBy('serial_number')
            ->get()
            ->map(function (Device $olt) use ($onlineStatuses): array {
                $ports = $olt->ports->map(function ($port) use ($onlineStatuses): array {
                    $onus = $port->onus;
                    $onlineCount = $onus->filter(
                        fn (Device $onu): bool => in_array((string) $onu->onu_oper_status, $onlineStatuses, true)
                    )->count();

                    return [
                        'id' => $port->id,
                        'label' => $port->label ?? "{$port->card_index}/{$port->pon_index}",
                        'oper_status' => $port->oper_status,
                        'onu_total' => $onus->count(),
                        'onu_online' => $onlineCount,
                        'onus' => $this->mapOnus($onus, $onlineStatuses),
                    ];
                })->values()->all();

                $looseOnus = Device::query()
                    ->where('type', 'onu')
                    ->where('olt_id', $olt->id)
                    ->whereNull('olt_port_id')
                    ->with('customer:id,name,customer_code,status')
                    ->orderBy('serial_number')
                    ->limit(self::ONU_PREVIEW_LIMIT + 1)
                    ->get();

                $oltOnus = Device::query()
                    ->where('olt_id', $olt->id)
                    ->where('type', 'onu')
                    ->get();
                $oltOnuOnline = $oltOnus->filter(
                    fn (Device $onu): bool => in_array((string) $onu->onu_oper_status, $onlineStatuses, true)
                )->count();

                return [
                    'id' => $olt->id,
                    'label' => $olt->adminLabel(),
                    'management_ip' => $olt->management_ip,
                    'health' => is_array($olt->olt_health) ? ($olt->olt_health['status'] ?? null) : null,
                    'onu_total' => $oltOnus->count(),
                    'onu_online' => $oltOnuOnline,
                    'edit_url' => OltResource::getUrl('edit', ['record' => $olt]),
                    'ports' => $ports,
                    'loose_onus' => $this->mapOnus($looseOnus, $onlineStatuses),
                ];
            })
            ->values()
            ->all();

        $geo = Area::query()
            ->with([
                'zones' => fn ($q) => $q->orderBy('name')->with([
                    'subzones' => fn ($sq) => $sq->orderBy('name'),
                ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Area $area): array {
                $areaCustomers = Customer::query()->where('area_id', $area->id)->count();

                $zones = $area->zones->map(function ($zone) use ($area): array {
                    $zoneCustomers = Customer::query()
                        ->where('area_id', $area->id)
                        ->where('zone_id', $zone->id)
                        ->count();

                    $subzones = $zone->subzones->map(function ($subzone) use ($area, $zone): array {
                        $count = Customer::query()
                            ->where('area_id', $area->id)
                            ->where('zone_id', $zone->id)
                            ->where('subzone_id', $subzone->id)
                            ->count();

                        return [
                            'id' => $subzone->id,
                            'name' => $subzone->name,
                            'customers' => $count,
                        ];
                    })->values()->all();

                    return [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'customers' => $zoneCustomers,
                        'subzones' => $subzones,
                    ];
                })->values()->all();

                return [
                    'id' => $area->id,
                    'name' => $area->name,
                    'customers' => $areaCustomers,
                    'zones' => $zones,
                ];
            })
            ->values()
            ->all();

        $onuTotal = Device::query()->where('type', 'onu')->count();
        $onuOnline = Device::query()
            ->where('type', 'onu')
            ->whereIn('onu_oper_status', $onlineStatuses)
            ->count();

        return [
            'summary' => [
                'mikrotik' => count($mikrotik),
                'olts' => count($olts),
                'onus' => $onuTotal,
                'onus_online' => $onuOnline,
                'areas' => count($geo),
                'customers' => Customer::query()->count(),
            ],
            'mikrotik' => $mikrotik,
            'olts' => $olts,
            'geo' => $geo,
        ];
    }

    /**
     * @param  Collection<int, Device>  $onus
     * @param  list<string>  $onlineStatuses
     * @return array{items: list<array<string, mixed>>, total: int, truncated: bool}
     */
    private function mapOnus(Collection $onus, array $onlineStatuses): array
    {
        $total = $onus->count();
        $slice = $onus->take(self::ONU_PREVIEW_LIMIT);

        $items = $slice->map(function (Device $onu) use ($onlineStatuses): array {
            $online = in_array((string) $onu->onu_oper_status, $onlineStatuses, true);
            $customer = $onu->customer;

            return [
                'id' => $onu->id,
                'label' => $onu->adminLabel(),
                'serial' => $onu->serial_number,
                'online' => $online,
                'status' => $onu->onu_oper_status,
                'rx_dbm' => $onu->rx_power_dbm,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'code' => $customer->customer_code,
                    'url' => CustomerResource::getUrl('view', ['record' => $customer]),
                ] : null,
            ];
        })->values()->all();

        return [
            'items' => $items,
            'total' => $total,
            'truncated' => $total > self::ONU_PREVIEW_LIMIT,
        ];
    }
}
