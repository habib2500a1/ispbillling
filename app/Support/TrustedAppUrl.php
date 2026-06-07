<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class TrustedAppUrl
{
    /**
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        $hosts = [];

        foreach ([
            (string) config('app.url'),
            (string) config('domains.landing', ''),
            ...self::splitList((string) env('APP_PREVIOUS_URLS', '')),
            ...self::splitList((string) env('ISP_ALLOWED_HOSTS', '')),
        ] as $candidate) {
            $host = self::hostFromCandidate($candidate);

            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    public static function applyFromRequest(?Request $request = null): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $request ??= request();
        $host = strtolower($request->getHost());

        if (! in_array($host, self::allowedHosts(), true)) {
            return;
        }

        $scheme = $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https'
            ? 'https'
            : 'http';

        URL::forceRootUrl("{$scheme}://{$host}");
    }

    /**
     * @return list<string>
     */
    public static function mergePreviousUrls(?string $currentUrl, ?string $newUrl, ?string $existingList = null): string
    {
        $previous = self::splitList($existingList ?? '');
        $currentHost = self::hostFromCandidate((string) $currentUrl);
        $newHost = self::hostFromCandidate((string) $newUrl);

        if ($currentHost !== null && $currentHost !== $newHost) {
            $previous[] = rtrim((string) $currentUrl, '/');
        }

        $unique = [];

        foreach ($previous as $url) {
            $normalized = rtrim(trim($url), '/');
            $host = self::hostFromCandidate($normalized);

            if ($host === null || $host === $newHost) {
                continue;
            }

            $unique[$host] = $normalized;
        }

        return implode(',', array_values($unique));
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private static function hostFromCandidate(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        if (! str_contains($candidate, '://')) {
            return strtolower($candidate);
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }
}
