<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Remove stale domain-scoped session cookies after SESSION_DOMAIN was cleared.
 * Only targets the old explicit host domain — never host-only (current) cookies.
 */
final class ClearLegacySessionCookies
{
    /** @var list<string> */
    private const LEGACY_NAMES = [
        'ispplatform_session',
        'ispplatform_admin_session',
        'ispplatform_sess_v2',
        'laravel_session',
    ];

    public static function apply(Response $response): Response
    {
        $legacyDomain = strtolower(trim((string) config('session.legacy_domain', '')));

        if ($legacyDomain === '') {
            return $response;
        }

        $legacyDomains = array_unique([
            $legacyDomain,
            str_starts_with($legacyDomain, '.') ? $legacyDomain : '.'.$legacyDomain,
        ]);

        foreach (self::LEGACY_NAMES as $name) {
            foreach ($legacyDomains as $domain) {
                $response->headers->clearCookie(
                    $name,
                    '/',
                    $domain,
                    true,
                    true,
                    false,
                    'lax',
                );
            }
        }

        return $response;
    }
}
