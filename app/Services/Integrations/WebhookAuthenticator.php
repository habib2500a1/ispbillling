<?php

namespace App\Services\Integrations;

use Illuminate\Http\Request;

/**
 * Authorize inbound webhooks: tenant HMAC (preferred) or legacy shared secret header.
 */
final class WebhookAuthenticator
{
    public function __construct(
        private readonly TenantApiSettingsService $tenantApi,
    ) {}

    public function authorize(
        Request $request,
        string $configSecretKey,
        ?int $tenantId = null,
    ): bool {
        $tenantId ??= (int) ($request->input('tenant_id') ?? 0);

        if ($tenantId > 0 && $this->tenantApi->hasWebhookHmacSecret($tenantId)) {
            $sig = $request->header('X-ISP-Signature')
                ?? $request->header('X-Webhook-Signature');

            if ($this->tenantApi->verifyWebhookSignature(
                $tenantId,
                $request->getContent(),
                is_string($sig) ? $sig : null,
            )) {
                return true;
            }

            return false;
        }

        $secret = (string) config($configSecretKey);
        if ($secret === '') {
            return ! app()->isProduction();
        }

        $provided = (string) ($request->header('X-ISP-Webhook-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? '');

        return hash_equals($secret, $provided);
    }

    public function missingSecretInProduction(string $configSecretKey, ?int $tenantId = null): bool
    {
        if (! app()->isProduction()) {
            return false;
        }

        $tenantId ??= 0;
        if ($tenantId > 0 && $this->tenantApi->hasWebhookHmacSecret($tenantId)) {
            return false;
        }

        return (string) config($configSecretKey) === '';
    }
}
