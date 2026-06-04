<?php

namespace App\Services\Platform;

use Carbon\Carbon;
use Illuminate\Support\Str;

final class PlatformLicenseService
{
    public const DEPLOYMENT_SAAS = 'saas';

    public const DEPLOYMENT_ON_PREMISE = 'on_premise';

    public function deploymentMode(): string
    {
        $mode = strtolower(trim((string) config('isp.deployment_mode', self::DEPLOYMENT_SAAS)));

        return $mode === self::DEPLOYMENT_ON_PREMISE
            ? self::DEPLOYMENT_ON_PREMISE
            : self::DEPLOYMENT_SAAS;
    }

    public function isSaasDeployment(): bool
    {
        return $this->deploymentMode() === self::DEPLOYMENT_SAAS;
    }

    public function isOnPremiseDeployment(): bool
    {
        return $this->deploymentMode() === self::DEPLOYMENT_ON_PREMISE;
    }

    public function isEnforced(): bool
    {
        if (! (bool) config('isp.license.enforce', false)) {
            return false;
        }

        return $this->isOnPremiseDeployment();
    }

    public function licenseKey(): string
    {
        return trim((string) config('isp.license.key', ''));
    }

    /**
     * @return array{valid: bool, message: string, payload: ?array<string, mixed>}
     */
    public function validate(?string $host = null): array
    {
        if (! $this->isEnforced()) {
            return ['valid' => true, 'message' => 'License not required (SaaS / enforcement off).', 'payload' => null];
        }

        $key = $this->licenseKey();
        if ($key === '') {
            return ['valid' => false, 'message' => 'ISP_LICENSE_KEY is missing.', 'payload' => null];
        }

        $payload = $this->parseAndVerifyKey($key);
        if ($payload === null) {
            return ['valid' => false, 'message' => 'Invalid or tampered license key.', 'payload' => null];
        }

        $expires = (string) ($payload['expires'] ?? '');
        if ($expires !== '' && Carbon::parse($expires)->endOfDay()->isPast()) {
            return ['valid' => false, 'message' => 'License expired on '.$expires.'.', 'payload' => $payload];
        }

        $host = $this->normalizeHost($host ?? (string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $allowed = array_map(
            fn (mixed $d): string => $this->normalizeHost((string) $d),
            (array) ($payload['domains'] ?? []),
        );

        if ($allowed !== [] && ! $this->hostMatches($host, $allowed)) {
            return [
                'valid' => false,
                'message' => 'License not valid for host '.$host.'.',
                'payload' => $payload,
            ];
        }

        return ['valid' => true, 'message' => 'License active.', 'payload' => $payload];
    }

    public function maxTenants(): ?int
    {
        $result = $this->validate();
        if (! $result['valid'] || ! is_array($result['payload'])) {
            return null;
        }

        $max = $result['payload']['max_tenants'] ?? null;

        return is_numeric($max) ? max(1, (int) $max) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseAndVerifyKey(string $key): ?array
    {
        $parts = explode('.', trim($key), 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $sigB64] = $parts;
        $json = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        $signature = base64_decode(strtr($sigB64, '-_', '+/'), true);

        if ($json === false || $signature === false) {
            return null;
        }

        $publicKey = $this->publicKeyPem();
        if ($publicKey === null) {
            return null;
        }

        $ok = openssl_verify($json, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    private function publicKeyPem(): ?string
    {
        $path = (string) config('isp.license.public_key_path', '');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $pem = file_get_contents($path);

        return is_string($pem) && str_contains($pem, 'BEGIN PUBLIC KEY') ? $pem : null;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;

        return $host;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function hostMatches(string $host, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($pattern === $host) {
                return true;
            }
            if (str_starts_with($pattern, '*.') && Str::endsWith($host, substr($pattern, 1))) {
                return true;
            }
        }

        return false;
    }
}
