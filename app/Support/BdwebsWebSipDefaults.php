<?php

namespace App\Support;

/**
 * BDWebs / PortSIP: mobile app uses UDP 5060; browser WebSIP needs WSS on the SIP hostname.
 * When BDWebs does not publish WSS, we try common paths on the same host as sip_domain.
 */
final class BdwebsWebSipDefaults
{
    /**
     * Common WSS paths (same credentials as PortSIP).
     *
     * @return list<string>
     */
    public static function wssCandidatesFor(string $host): array
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return [];
        }

        $candidates = [
            "wss://{$host}:7443/ws",
            "wss://{$host}:7443/wss",
            "wss://{$host}:443/ws",
            "wss://{$host}:443/wss",
            "wss://{$host}/ws",
            "wss://{$host}/wss",
            "wss://{$host}:8089/ws",
            "wss://{$host}:5061/ws",
            "wss://{$host}:5063/ws",
        ];

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    public static function resolveWssUris(?string $explicit, ?string $sipDomain, ?string $sipServer): array
    {
        $uris = [];

        if (filled($explicit)) {
            $uris[] = trim((string) $explicit);
        }

        if (! config('call_center.websip_auto_wss_candidates', true)) {
            return array_values(array_unique(array_filter($uris)));
        }

        foreach ([$sipDomain, $sipServer] as $host) {
            if (! filled($host)) {
                continue;
            }
            $uris = array_merge($uris, self::wssCandidatesFor((string) $host));
        }

        return array_values(array_unique(array_filter($uris)));
    }

    private static function normalizeHost(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('#^wss?://#i', '', $host) ?? $host;
        $host = preg_replace('#/.*$#', '', $host) ?? $host;
        $host = preg_replace('#^sip:#i', '', $host) ?? $host;
        $host = preg_replace('#:.*$#', '', $host) ?? $host;

        return trim($host);
    }
}
