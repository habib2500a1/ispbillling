<?php

namespace App\Support;

use App\Models\User;

final class AdminBroadcastConfig
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user): array
    {
        $driver = (string) config('broadcasting.default', 'log');
        if (! $user || in_array($driver, ['log', 'null'], true)) {
            return ['enabled' => false];
        }

        $key = (string) config('broadcasting.connections.pusher.key', '');
        if ($key === '') {
            return ['enabled' => false];
        }

        $scheme = (string) (env('PUSHER_PUBLIC_SCHEME') ?: config('broadcasting.connections.pusher.options.scheme', 'https'));
        $host = (string) (env('PUSHER_PUBLIC_HOST') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost());
        $port = (int) (env('PUSHER_PUBLIC_PORT') ?: ($scheme === 'https' ? 443 : 80));
        $cluster = (string) config('broadcasting.connections.pusher.options.cluster', 'mt1');

        return [
            'enabled' => true,
            'tenantId' => (int) $user->tenant_id,
            'driver' => $driver,
            'key' => $key,
            'cluster' => $cluster,
            'wsHost' => $host,
            'wsPort' => $port,
            'wssPort' => $port,
            'forceTLS' => $scheme === 'https',
            'wsPath' => (string) config('broadcasting.admin_ws_path', '/ws'),
            'authEndpoint' => '/broadcasting/auth',
        ];
    }
}
