<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Support\MacAddress;

/**
 * When ONU MAC changes (replace workflow), archive old MAC — never auto-delete on offline.
 */
final class OnuMacArchiveService
{
    public function archiveIfMacChanged(Device $onu, ?string $incomingMac): void
    {
        if (! config('onu_management.mac_archive.enabled', true)) {
            return;
        }

        if (! filled($incomingMac)) {
            return;
        }

        $newMac = MacAddress::normalizeColon($incomingMac) ?? $incomingMac;
        $oldMac = filled($onu->mac_address)
            ? (MacAddress::normalizeColon($onu->mac_address) ?? $onu->mac_address)
            : null;

        if ($oldMac === null || strcasecmp($oldMac, $newMac) === 0) {
            return;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $archive = is_array($meta['mac_archive'] ?? null) ? $meta['mac_archive'] : [];

        $archive[] = [
            'mac' => $oldMac,
            'replaced_by' => $newMac,
            'archived_at' => now()->toIso8601String(),
            'customer_id' => $onu->customer_id,
        ];

        $max = max(1, (int) config('onu_management.mac_archive.max_entries', 10));
        $meta['mac_archive'] = array_slice($archive, -$max);
        $meta['mac_replaced_at'] = now()->toIso8601String();

        $onu->forceFill(['meta' => $meta])->saveQuietly();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function archivedMacs(Device $onu): array
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];

        return is_array($meta['mac_archive'] ?? null) ? $meta['mac_archive'] : [];
    }
}
