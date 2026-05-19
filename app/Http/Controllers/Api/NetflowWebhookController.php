<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Network\NetflowIngestService;
use App\Support\WebhookAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetflowWebhookController extends Controller
{
    public function store(Request $request, NetflowIngestService $ingest): JsonResponse
    {
        $secret = config('netflow.webhook_secret');
        WebhookAuth::authorizeHeader($request, is_string($secret) ? $secret : null, 'X-Netflow-Secret');

        if (! config('netflow.enabled')) {
            return response()->json(['message' => 'NetFlow ingestion disabled'], 503);
        }

        $payload = $request->validate([
            'exporter_ip' => ['nullable', 'string', 'max:45'],
            'flows' => ['required', 'array'],
            'flows.*.src' => ['sometimes', 'string'],
            'flows.*.src_ip' => ['sometimes', 'string'],
            'flows.*.dst' => ['sometimes', 'string'],
            'flows.*.dst_ip' => ['sometimes', 'string'],
            'flows.*.bytes' => ['sometimes', 'integer', 'min:0'],
            'flows.*.packets' => ['sometimes', 'integer', 'min:0'],
        ]);

        $result = $ingest->ingestPayload($payload);

        return response()->json([
            'message' => 'Flows ingested.',
            'inserted' => $result['inserted'],
        ]);
    }
}
