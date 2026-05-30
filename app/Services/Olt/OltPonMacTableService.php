<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Support\MacAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OltPonMacTableService
{
    /**
     * @return Collection<int, array{
     *     row_key: string,
     *     mac: string,
     *     vlan: ?int,
     *     port_id: string,
     *     onu_id: string,
     *     name: string,
     *     mac_type: string,
     *     olt_id: int,
     *     olt_label: string,
     *     synced_at: ?Carbon
     * }>
     */
    public static function rows(?int $oltId = null, ?string $search = null): Collection
    {
        $query = Device::query()
            ->where('type', 'onu')
            ->whereNotNull('olt_id')
            ->where(function (Builder $q): void {
                $q->whereNotNull('meta->pon_mac_entries')
                    ->orWhereNotNull('meta->fdb_macs');
            })
            ->with([
                'olt:id,display_name,management_ip,tenant_id',
                'oltPort:id,label,card_index,pon_index',
            ]);

        if ($oltId !== null) {
            $query->where('olt_id', $oltId);
        }

        $rows = collect();

        foreach ($query->get() as $onu) {
            foreach (static::entriesFromOnu($onu) as $entry) {
                $mac = static::formatMac($entry['mac'] ?? null);
                if ($mac === '—') {
                    continue;
                }

                $rows->push([
                    'row_key' => $onu->id.'-'.str_replace(':', '', $mac),
                    'mac' => $mac,
                    'vlan' => isset($entry['vlan']) ? (int) $entry['vlan'] : null,
                    'port_id' => static::ponPortId($onu),
                    'onu_id' => static::onuSlotLabel($onu),
                    'name' => static::onuName($onu),
                    'mac_type' => ucfirst((string) ($entry['type'] ?? 'dynamic')),
                    'olt_id' => (int) $onu->olt_id,
                    'olt_label' => OltMacTableService::oltLabel($onu),
                    'synced_at' => static::syncedAt($onu),
                ]);
            }
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $compact = strtoupper(str_replace([':', '-', '.', ' '], '', $term));
            $rows = $rows->filter(function (array $row) use ($term, $compact): bool {
                $haystack = strtoupper(implode(' ', [
                    $row['mac'],
                    $row['port_id'],
                    $row['onu_id'],
                    $row['name'],
                    $row['olt_label'],
                    (string) ($row['vlan'] ?? ''),
                ]));

                if (str_contains($haystack, strtoupper($term))) {
                    return true;
                }

                return $compact !== '' && str_contains(str_replace(':', '', $row['mac']), $compact);
            });
        }

        return $rows->sortBy('mac')->values();
    }

    /**
     * @return Collection<int, array{olt_id: int, olt_label: string, mac_count: int, last_seen: ?Carbon}>
     */
    public static function summaryByOlt(?int $oltId = null): Collection
    {
        return static::rows($oltId)
            ->groupBy('olt_id')
            ->map(function (Collection $group, int|string $oltId): array {
                $first = $group->first();
                $lastSeen = $group->max(fn (array $row): ?int => $row['synced_at']?->timestamp);

                return [
                    'olt_id' => (int) $oltId,
                    'olt_label' => (string) ($first['olt_label'] ?? 'OLT #'.$oltId),
                    'mac_count' => $group->count(),
                    'last_seen' => $lastSeen ? Carbon::createFromTimestamp($lastSeen) : null,
                ];
            })
            ->sortByDesc('mac_count')
            ->values();
    }

    public static function totalMacCount(?int $oltId = null): int
    {
        return static::rows($oltId)->count();
    }

    public static function formatMac(?string $mac): string
    {
        if (! filled($mac)) {
            return '—';
        }

        return MacAddress::normalizeColon($mac) ?? strtoupper($mac);
    }

    public static function ponPortId(Device $onu): string
    {
        if ($onu->oltPort?->label && preg_match('/PON/i', (string) $onu->oltPort->label)) {
            return strtoupper((string) $onu->oltPort->label);
        }

        if ($onu->pon_no !== null) {
            return sprintf('PON%02d', (int) $onu->pon_no);
        }

        return OltMacTableService::portLabel($onu);
    }

    public static function onuSlotLabel(Device $onu): string
    {
        $card = $onu->card_no ?? 1;

        if ($onu->onu_index !== null) {
            return $card.' / '.(int) $onu->onu_index;
        }

        if (preg_match('/(\d+)\s*\/\s*(\d+)/', (string) $onu->display_name, $m)) {
            return $m[1].' / '.$m[2];
        }

        return '—';
    }

    public static function onuName(Device $onu): string
    {
        if (filled($onu->display_name)) {
            return (string) $onu->display_name;
        }

        if ($onu->pon_no !== null && $onu->onu_index !== null) {
            return sprintf('ONU%02d/%d', (int) $onu->pon_no, (int) $onu->onu_index);
        }

        return 'ONU #'.$onu->id;
    }

    public static function syncedAt(Device $onu): ?Carbon
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $raw = $meta['fdb_synced_at'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{mac: string, vlan: ?int, type: string}>
     */
    private static function entriesFromOnu(Device $onu): array
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];

        $structured = $meta['pon_mac_entries'] ?? null;
        if (is_array($structured) && $structured !== []) {
            return array_values(array_filter($structured, fn ($entry): bool => is_array($entry) && filled($entry['mac'] ?? null)));
        }

        $legacy = is_array($meta['fdb_macs'] ?? null) ? $meta['fdb_macs'] : [];

        return array_map(
            fn (mixed $mac): array => [
                'mac' => (string) $mac,
                'vlan' => null,
                'type' => 'dynamic',
            ],
            $legacy,
        );
    }
}
