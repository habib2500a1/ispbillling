<?php

namespace App\Support;

use Illuminate\Http\Request;

final class WebhookAuth
{
    /**
     * Reject webhook when secret is missing in production (fail closed).
     */
    public static function abortIfSecretNotConfigured(?string $secret, string $configKey): void
    {
        if (filled($secret)) {
            return;
        }

        if (app()->environment('production')) {
            abort(503, "Webhook secret not configured ({$configKey})");
        }
    }

    public static function authorizeHeader(Request $request, ?string $secret, string $headerName): void
    {
        self::abortIfSecretNotConfigured($secret, $headerName);

        if (! filled($secret)) {
            return;
        }

        if ($request->header($headerName) !== $secret) {
            abort(401, 'Unauthorized');
        }
    }

    public static function authorizeOptical(Request $request, ?string $secret): void
    {
        self::abortIfSecretNotConfigured($secret, 'optical.webhook_secret');

        if (! filled($secret)) {
            return;
        }

        $provided = $request->header('X-Optical-Secret')
            ?? $request->header('X-ISP-Webhook-Secret')
            ?? $request->input('secret');

        if ($provided !== $secret) {
            abort(401, 'Unauthorized — check X-Optical-Secret header');
        }
    }
}
