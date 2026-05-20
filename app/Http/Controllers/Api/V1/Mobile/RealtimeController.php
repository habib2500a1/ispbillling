<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id ?? 0;
        $connection = config('broadcasting.default', 'log');
        $host = config('broadcasting.connections.reverb.options.host', '127.0.0.1');
        $port = (int) config('broadcasting.connections.reverb.options.port', 8080);
        $scheme = config('broadcasting.connections.reverb.options.scheme', 'http');
        $key = config('broadcasting.connections.reverb.key', '');

        return response()->json([
            'enabled' => $connection !== 'log' && filled($key),
            'driver' => $connection,
            'channel' => "tenant.{$tenantId}.mobile",
            'events' => ['payment_received', 'onu_signal_changed', 'router_alert', 'ticket_created'],
            'websocket' => [
                'host' => $host,
                'port' => $port,
                'scheme' => $scheme,
                'key' => $key,
            ],
            'polling_fallback_seconds' => 30,
        ]);
    }
}
