<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Optical\OnuOpticalWebhookIngestService;
use App\Support\WebhookAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnuOpticalWebhookController extends Controller
{
    public function store(Request $request, OnuOpticalWebhookIngestService $ingest): JsonResponse
    {
        $secret = config('optical.webhook_secret');
        WebhookAuth::authorizeOptical($request, is_string($secret) ? $secret : null);

        if (! config('optical.enabled', true)) {
            return response()->json(['message' => 'Optical monitoring disabled'], 503);
        }

        $payload = $request->all();
        if ($payload === []) {
            return response()->json(['message' => 'Empty body — send JSON with readings[] array'], 422);
        }

        $result = $ingest->ingest($payload);

        $status = $result['processed'] > 0 || $result['created'] > 0 ? 200 : 422;

        return response()->json([
            'message' => $result['processed'] > 0
                ? 'Optical readings ingested.'
                : 'No readings matched — see skipped_details (enable create_missing or add ONUs).',
            'processed' => $result['processed'],
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'skipped_details' => $result['skipped_details'],
            'hint' => $result['processed'] === 0 && $result['created'] === 0
                ? 'Send olt_id + create_missing:true, or match serial/customer_code/ppp_login to existing ONU.'
                : null,
        ], $status);
    }
}
