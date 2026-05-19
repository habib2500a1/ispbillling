<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Support\MacAddress;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OltMacTableService
{
    public static function baseQuery(): Builder
    {
        return Device::query()
            ->where('type', 'onu')
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->with([
                'olt:id,display_name,management_ip,tenant_id',
                'oltPort:id,label,card_index,pon_index',
            ]);
    }

    /**
     * @return Collection<int, array{olt_id: int, olt_label: string, mac_count: int, last_seen: ?\Illuminate\Support\Carbon}>
     */
    public static function summaryByOlt(?int $oltId = null): Collection
    {
        $query = static::baseQuery()
            ->selectRaw('olt_id, COUNT(*) as mac_count, MAX(COALESCE(last_polled_at, provisioned_at, updated_at)) as last_seen')
            ->whereNotNull('olt_id')
            ->groupBy('olt_id');

        if ($oltId !== null) {
            $query->where('olt_id', $oltId);
        }

        $rows = $query->get();
        $oltNames = Device::query()
            ->olts()
            ->whereIn('id', $rows->pluck('olt_id'))
            ->pluck('display_name', 'id');

        return $rows->map(function ($row) use ($oltNames): array {
            $id = (int) $row->olt_id;

            return [
                'olt_id' => $id,
                'olt_label' => (string) ($oltNames[$id] ?? 'OLT #'.$id),
                'mac_count' => (int) $row->mac_count,
                'last_seen' => $row->last_seen ? \Illuminate\Support\Carbon::parse($row->last_seen) : null,
            ];
        })->sortByDesc('mac_count')->values();
    }

    public static function totalMacCount(?int $oltId = null): int
    {
        $query = static::baseQuery();

        if ($oltId !== null) {
            $query->where('olt_id', $oltId);
        }

        return (int) $query->count();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function oltFilterOptions(): array
    {
        return Device::query()
            ->olts()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'management_ip'])
            ->map(fn (Device $olt): array => [
                'id' => $olt->id,
                'label' => $olt->adminLabel().($olt->management_ip ? ' · '.$olt->management_ip : ''),
            ])
            ->all();
    }

    public static function applySearch(Builder $query, ?string $search): Builder
    {
        $term = trim((string) $search);
        if ($term === '') {
            return $query;
        }

        $compact = strtoupper(str_replace([':', '-', '.'], '', $term));

        return $query->where(function (Builder $q) use ($term, $compact): void {
            $q->where('display_name', 'like', '%'.$term.'%')
                ->orWhere('mac_address', 'like', '%'.$term.'%')
                ->orWhere('serial_number', 'like', '%'.$term.'%')
                ->orWhereHas('olt', function (Builder $olt) use ($term): void {
                    $olt->where('display_name', 'like', '%'.$term.'%')
                        ->orWhere('management_ip', 'like', '%'.$term.'%');
                });

            if ($compact !== '') {
                $q->orWhereRaw("REPLACE(UPPER(COALESCE(mac_address, '')), ':', '') LIKE ?", ['%'.$compact.'%']);
            }
        });
    }

    public static function formatMac(?string $mac): string
    {
        if (! filled($mac)) {
            return '—';
        }

        return MacAddress::normalizeColon($mac) ?? strtoupper($mac);
    }

    public static function portLabel(Device $onu): string
    {
        if ($onu->oltPort?->label) {
            return (string) $onu->oltPort->label;
        }

        if ($onu->card_no !== null && $onu->pon_no !== null) {
            return $onu->card_no.'/'.$onu->pon_no;
        }

        if ($onu->pon_no !== null) {
            return '0/'.$onu->pon_no;
        }

        return '—';
    }

    public static function onuIndexLabel(Device $onu): ?string
    {
        if ($onu->onu_index === null) {
            return null;
        }

        return 'ONU '.$onu->onu_index;
    }

    public static function interfaceLabel(Device $onu): string
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $bdcom = $meta['bdcom_label'] ?? null;
        if (is_string($bdcom) && $bdcom !== '') {
            return strtoupper($bdcom);
        }

        if (filled($onu->display_name) && preg_match('/^EPON\d+\/\d+/i', (string) $onu->display_name)) {
            return strtoupper((string) $onu->display_name);
        }

        if ($onu->card_no !== null && $onu->pon_no !== null) {
            $iface = 'EPON'.$onu->card_no.'/'.$onu->pon_no;
            if ($onu->onu_index !== null) {
                $iface .= ':'.$onu->onu_index;
            }

            return $iface;
        }

        return '—';
    }

    public static function oltLabel(Device $onu): string
    {
        if ($onu->olt) {
            return $onu->olt->adminLabel();
        }

        return '—';
    }

    public static function lastSeenAt(Device $onu): ?\Illuminate\Support\Carbon
    {
        return $onu->last_polled_at ?? $onu->last_snmp_poll_at ?? $onu->updated_at;
    }

    public static function learnedAt(Device $onu): ?\Illuminate\Support\Carbon
    {
        return $onu->provisioned_at ?? $onu->created_at;
    }
}
