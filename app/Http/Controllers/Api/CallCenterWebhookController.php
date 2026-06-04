<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CallCenter\CallCenterIngestService;
use App\Services\Integrations\WebhookAuthenticator;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallCenterWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        CallCenterIngestService $ingest,
        WebhookAuthenticator $webhooks,
    ): JsonResponse {
        $tenantId = (int) ($request->input('tenant_id') ?? TenantResolver::currentTenantId() ?? 1);

        if ($webhooks->missingSecretInProduction('call_center.webhook_secret', $tenantId)) {
            return response()->json(['message' => 'Webhook secret not configured'], 503);
        }

        if (! $webhooks->authorize($request, 'call_center.webhook_secret', $tenantId)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $log = $ingest->ingest($request->all(), $tenantId);

        return response()->json([
            'ok' => true,
            'call_log_id' => $log->id,
        ]);
    }
}
