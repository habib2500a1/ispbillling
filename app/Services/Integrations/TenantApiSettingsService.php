<?php

namespace App\Services\Integrations;

use App\Models\Tenant;
use App\Services\Tenant\TenantScopedConfig;
use App\Support\TenantResolver;
use Illuminate\Support\Str;

/**
 * Per-tenant API integration settings (Sheba-Fi "API Configuration" parity, safer storage).
 */
final class TenantApiSettingsService
{
    public const CONFIG_API_HOST = 'integrations.api_host';

    public const CONFIG_WEBHOOK_HMAC = 'integrations.webhook_hmac_secret';

    public function tenantId(): int
    {
        return TenantResolver::requiredTenantId();
    }

    public function getRawApiHostOverride(?int $tenantId = null): string
    {
        $tenantId ??= $this->tenantId();

        return $this->get($tenantId, self::CONFIG_API_HOST);
    }

    public function apiHost(?int $tenantId = null): string
    {
        $tenantId ??= $this->tenantId();
        $override = trim($this->get($tenantId, self::CONFIG_API_HOST));

        if ($override !== '') {
            return strtolower($override);
        }

        $tenant = Tenant::query()->find($tenantId);
        $slug = $tenant?->slug ? Str::slug((string) $tenant->slug) : 'tenant';
        $base = trim((string) config('isp.tenant_base_domain', ''));

        if ($base !== '') {
            return $slug.'.'.ltrim($base, '.');
        }

        return parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
    }

    public function apiBaseUrl(?int $tenantId = null): string
    {
        $host = $this->apiHost($tenantId);
        $scheme = request()->isSecure() || config('app.env') === 'production' ? 'https' : 'http';

        return $scheme.'://'.$host;
    }

    public function hasWebhookHmacSecret(?int $tenantId = null): bool
    {
        $tenantId ??= $this->tenantId();

        return $this->get($tenantId, self::CONFIG_WEBHOOK_HMAC) !== '';
    }

    public function maskedWebhookHmacSecret(?int $tenantId = null): string
    {
        $tenantId ??= $this->tenantId();
        $secret = $this->get($tenantId, self::CONFIG_WEBHOOK_HMAC);

        if ($secret === '') {
            return '';
        }

        return '••••••••'.substr($secret, -4);
    }

    /**
     * @return array{plaintext: string, masked: string}
     */
    public function regenerateWebhookHmacSecret(?int $tenantId = null): array
    {
        $tenantId ??= $this->tenantId();
        $plaintext = Str::random(64);
        $this->put($tenantId, self::CONFIG_WEBHOOK_HMAC, $plaintext);

        return [
            'plaintext' => $plaintext,
            'masked' => $this->maskedWebhookHmacSecret($tenantId),
        ];
    }

    public function saveApiHost(?int $tenantId, ?string $host): void
    {
        $tenantId ??= $this->tenantId();
        $host = strtolower(trim((string) $host));
        $host = preg_replace('#^https?://#', '', $host ?? '') ?? '';
        $host = rtrim($host, '/');

        if ($host === '') {
            $this->forget($tenantId, self::CONFIG_API_HOST);

            return;
        }

        $this->put($tenantId, self::CONFIG_API_HOST, $host);
    }

    /**
     * Verify X-ISP-Signature: t={unix},v1={hex} over raw body (Stripe-style).
     */
    public function verifyWebhookSignature(
        int $tenantId,
        string $rawPayload,
        ?string $signatureHeader,
        int $maxAgeSeconds = 300,
    ): bool {
        $secret = $this->get($tenantId, self::CONFIG_WEBHOOK_HMAC);
        if ($secret === '' || $signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $timestamp = null;
        $signature = null;

        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $signature = substr($part, 3);
            }
        }

        if ($timestamp === null || $signature === null || $timestamp < 1) {
            return false;
        }

        if (abs(time() - $timestamp) > $maxAgeSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret);

        return hash_equals($expected, $signature);
    }

    private function get(int $tenantId, string $key): string
    {
        TenantScopedConfig::apply($tenantId);
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }

    private function put(int $tenantId, string $key, string $value): void
    {
        TenantScopedConfig::put($tenantId, $key, $value);
        TenantScopedConfig::apply($tenantId);
    }

    private function forget(int $tenantId, string $key): void
    {
        \App\Models\AppSetting::query()
            ->where('key', "tenant.{$tenantId}.{$key}")
            ->delete();
        TenantScopedConfig::apply($tenantId);
    }
}
