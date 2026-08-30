<?php

namespace App\Services\Saas;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CaddyDomainSync
{
    public function sync(): int
    {
        $admin = rtrim((string) env('CADDY_ADMIN_URL', 'http://caddy:2019'), '/');
        $hosts = SaasDomain::registeredHosts();
        if ($hosts === []) {
            return 0;
        }

        try {
            $routes = Http::timeout(5)->acceptJson()->get($admin.'/config/apps/http/servers/srv0/routes');
            if (! $routes->successful() || ! is_array($routes->json())) {
                return 0;
            }
        } catch (\Throwable $e) {
            Log::debug('Caddy domain sync skipped: '.$e->getMessage());

            return 0;
        }

        $existing = [];
        $template = null;
        $platform = parse_url((string) config('app.url'), PHP_URL_HOST);

        foreach ($routes->json() as $route) {
            foreach ($route['match'][0]['host'] ?? [] as $host) {
                $existing[strtolower((string) $host)] = true;
                if ($template === null && $platform && strcasecmp((string) $host, (string) $platform) === 0) {
                    $template = $route;
                }
            }
        }

        $template ??= $routes->json()[0] ?? null;
        if (! is_array($template)) {
            return 0;
        }

        $added = 0;
        foreach ($hosts as $host) {
            if (isset($existing[$host])) {
                continue;
            }

            $payload = $template;
            $payload['match'] = [['host' => [$host]]];
            $payload['terminal'] = true;

            try {
                $ok = Http::timeout(5)->acceptJson()->withHeaders(['Content-Type' => 'application/json'])
                    ->post($admin.'/config/apps/http/servers/srv0/routes', $payload);
                if ($ok->successful() || $ok->status() === 200) {
                    $existing[$host] = true;
                    $added++;
                }
            } catch (\Throwable $e) {
                Log::warning('Caddy domain add failed for '.$host.': '.$e->getMessage());
            }
        }

        return $added;
    }
}
