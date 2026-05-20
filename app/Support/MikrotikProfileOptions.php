<?php

namespace App\Support;

use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Support\Facades\Cache;

final class MikrotikProfileOptions
{
    /**
     * @return array<string, string> profile name => profile name
     */
    public static function forServer(?int $serverId): array
    {
        if ($serverId === null || $serverId <= 0) {
            return [];
        }

        return Cache::remember(
            "mikrotik_profiles:{$serverId}",
            now()->addMinutes(10),
            function () use ($serverId): array {
                $server = MikrotikServer::query()->withoutGlobalScopes()->find($serverId);
                if ($server === null || ! $server->is_enabled) {
                    return [];
                }

                try {
                    $client = app(MikrotikServerService::class)->makeClient($server);
                    $rows = $client->query('/ppp/profile/print')->read();
                } catch (\Throwable) {
                    return [];
                }

                $options = [];
                if (! is_array($rows)) {
                    return [];
                }

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $name = $row['name'] ?? null;
                    if (is_string($name) && trim($name) !== '') {
                        $name = trim($name);
                        $options[$name] = $name;
                    }
                }

                ksort($options, SORT_NATURAL);

                return $options;
            },
        );
    }

    public static function forget(?int $serverId): void
    {
        if ($serverId !== null && $serverId > 0) {
            Cache::forget("mikrotik_profiles:{$serverId}");
        }
    }
}
