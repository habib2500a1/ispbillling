<?php

namespace App\Services\CallCenter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound voice blast / IVR call — pluggable driver (log, HTTP webhook to BDWebs/PortSIP API, etc.).
 */
final class VoiceCallGateway
{
    public function enabled(): bool
    {
        return (bool) config('call_center.voice_call.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function placeCall(
        int $tenantId,
        string $phone,
        string $message,
        ?string $audioUrl = null,
        array $context = [],
    ): bool {
        if (! $this->enabled()) {
            Log::info('voice_call.skipped_disabled', ['phone' => $phone, 'tenant_id' => $tenantId]);

            return false;
        }

        $phone = preg_replace('/\D+/', '', $phone);
        if ($phone === '') {
            return false;
        }

        $driver = (string) config('call_center.voice_call.driver', 'log_only');

        return match ($driver) {
            'http_webhook' => $this->viaWebhook($tenantId, $phone, $message, $audioUrl, $context),
            default => $this->logOnly($tenantId, $phone, $message, $audioUrl, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logOnly(int $tenantId, string $phone, string $message, ?string $audioUrl, array $context): bool
    {
        Log::info('voice_call.log_only', [
            'tenant_id' => $tenantId,
            'phone' => $phone,
            'message' => mb_substr($message, 0, 500),
            'audio_url' => $audioUrl,
            'context' => $context,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function viaWebhook(int $tenantId, string $phone, string $message, ?string $audioUrl, array $context): bool
    {
        $url = trim((string) config('call_center.voice_call.webhook_url', ''));
        if ($url === '') {
            Log::warning('voice_call.webhook_url_missing');

            return false;
        }

        $payload = array_merge([
            'tenant_id' => $tenantId,
            'phone' => $phone,
            'message' => $message,
            'audio_url' => $audioUrl,
        ], $context);

        $request = Http::timeout((int) config('call_center.voice_call.timeout', 30))
            ->acceptJson()
            ->asJson();

        $secret = (string) config('call_center.voice_call.webhook_secret', '');
        if ($secret !== '') {
            $request = $request->withHeaders(['X-ISP-Voice-Webhook-Secret' => $secret]);
        }

        $response = $request->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('voice_call.webhook_failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return true;
    }
}
