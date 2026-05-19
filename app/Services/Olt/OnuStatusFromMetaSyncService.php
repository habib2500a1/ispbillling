<?php

namespace App\Services\Olt;

use App\Models\Device;

/**
 * Flattens NMS / script values from `devices.meta` into portal columns (`onu_oper_status`, `offline_reason`).
 *
 * Keys are configurable under `olt_vendors.device_meta_portal_keys` (default: portal_onu_oper_status, portal_offline_reason).
 */
final class OnuStatusFromMetaSyncService
{
    public function syncChunk(int $chunkSize = 100): int
    {
        $updated = 0;

        Device::query()
            ->withoutGlobalScopes()
            ->where('type', 'onu')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                foreach ($rows as $onu) {
                    if (! $this->applyMetaToDevice($onu)) {
                        continue;
                    }
                    $updated++;
                }
            });

        return $updated;
    }

    public function applyMetaToDevice(Device $onu): bool
    {
        $meta = $onu->meta;
        if (! is_array($meta)) {
            return false;
        }

        $keys = config('olt_vendors.device_meta_portal_keys', []);
        $operKey = is_string($keys['oper_status'] ?? null) ? $keys['oper_status'] : 'portal_onu_oper_status';
        $reasonKey = is_string($keys['offline_reason'] ?? null) ? $keys['offline_reason'] : 'portal_offline_reason';

        $dirty = false;

        if (array_key_exists($operKey, $meta)) {
            $raw = $meta[$operKey];
            $status = is_string($raw) ? mb_substr(trim($raw), 0, 24) : 'unknown';
            if ($status === '') {
                $status = 'unknown';
            }
            if ($onu->onu_oper_status !== $status) {
                $onu->onu_oper_status = $status;
                $dirty = true;
            }
        }

        if (array_key_exists($reasonKey, $meta)) {
            $raw = $meta[$reasonKey];
            $reason = null;
            if (is_string($raw)) {
                $t = trim($raw);
                $reason = $t === '' ? null : mb_substr($t, 0, 2000);
            }
            if (($onu->offline_reason ?? '') !== ($reason ?? '')) {
                $onu->offline_reason = $reason;
                $dirty = true;
            }
        }

        if (! $dirty) {
            return false;
        }

        $onu->last_polled_at = now();
        $onu->saveQuietly();

        return true;
    }
}
