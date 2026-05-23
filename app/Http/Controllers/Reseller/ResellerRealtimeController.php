<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerRealtimeController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        $reseller = $request->user('reseller');
        $tenantId = (int) $reseller->tenant_id;
        $connection = config('broadcasting.default', 'log');
        $key = config('broadcasting.connections.reverb.key', config('broadcasting.connections.pusher.key', ''));

        return response()->json([
            'enabled' => $connection !== 'log' && filled($key),
            'channel' => "tenant.{$tenantId}.reseller.{$reseller->id}",
            'events' => ['payment_received', 'onu_signal_changed', 'router_alert'],
            'polling_fallback_seconds' => 20,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $reseller = $request->user('reseller');
        $since = $request->query('since');

        $customerIds = $reseller->customers()->pluck('id');

        $payments = Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->latest()
            ->limit(10)
            ->get(['id', 'customer_id', 'amount', 'paid_at', 'created_at']);

        return response()->json([
            'payments' => $payments,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
