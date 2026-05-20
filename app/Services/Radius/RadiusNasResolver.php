<?php

namespace App\Services\Radius;

use App\Models\MikrotikServer;
use Illuminate\Support\Facades\Cache;

/**
 * Maps FreeRADIUS NAS-IP-Address (radacct.nasipaddress) to panel MikroTik servers.
 */
final class RadiusNasResolver
{
    /**
     * @return array<string, int> normalized NAS IP => mikrotik_server_id
     */
    public function nasMapForTenant(int $tenantId): array
    {
        return Cache::remember(
            'radius:nas_map:'.$tenantId,
            now()->addMinutes(5),
            function () use ($tenantId): array {
                $map = [];

                MikrotikServer::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->get(['id', 'host', 'radius_nas_ip', 'is_enabled'])
                    ->each(function (MikrotikServer $server) use (&$map): void {
                        foreach ($this->nasIpsForServer($server) as $ip) {
                            $map[$ip] = (int) $server->id;
                        }
                    });

                return $map;
            },
        );
    }

    /**
     * @return list<string>
     */
    public function nasIpsForTenant(int $tenantId): array
    {
        return array_keys($this->nasMapForTenant($tenantId));
    }

    public function resolveServerId(int $tenantId, string $nasIp): ?int
    {
        $normalized = $this->normalizeNasIp($nasIp);
        if ($normalized === '') {
            return null;
        }

        return $this->nasMapForTenant($tenantId)[$normalized] ?? null;
    }

    public static function forgetCache(int $tenantId): void
    {
        Cache::forget('radius:nas_map:'.$tenantId);
    }

    /**
     * @return list<string>
     */
    private function nasIpsForServer(MikrotikServer $server): array
    {
        $ips = [];

        if (filled($server->radius_nas_ip)) {
            $ips[] = $this->normalizeNasIp((string) $server->radius_nas_ip);
        }

        $ips[] = $this->normalizeNasIp((string) $server->host);

        return array_values(array_unique(array_filter($ips)));
    }

    private function normalizeNasIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }

        if (str_contains($ip, ':') && ! str_contains($ip, '::')) {
            $ip = explode(':', $ip)[0];
        }

        return $ip;
    }
}
