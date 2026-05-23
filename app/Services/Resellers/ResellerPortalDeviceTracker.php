<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ResellerPortalDeviceTracker
{
    public function recordLogin(Reseller $reseller, Request $request): void
    {
        $devices = is_array($reseller->portal_devices) ? $reseller->portal_devices : [];
        $fingerprint = sha1(implode('|', [
            (string) $request->userAgent(),
            (string) $request->ip(),
        ]));

        $entry = [
            'id' => $fingerprint,
            'ip' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
            'last_seen_at' => now()->toIso8601String(),
            'login_count' => 1,
        ];

        $found = false;
        foreach ($devices as $i => $device) {
            if (($device['id'] ?? '') === $fingerprint) {
                $devices[$i]['last_seen_at'] = $entry['last_seen_at'];
                $devices[$i]['login_count'] = (int) ($device['login_count'] ?? 0) + 1;
                $devices[$i]['ip'] = $entry['ip'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            array_unshift($devices, $entry);
        }

        $reseller->forceFill([
            'portal_devices' => array_slice($devices, 0, 20),
            'portal_last_login_at' => now(),
        ])->save();
    }
}
