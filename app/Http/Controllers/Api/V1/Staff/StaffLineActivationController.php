<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Subscribers\CustomerLineActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffLineActivationController extends Controller
{
    public function store(Request $request, Customer $customer, CustomerLineActivationService $service): JsonResponse
    {
        $validated = $request->validate([
            'line_charge' => ['nullable', 'numeric', 'min:0'],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'device_charge' => ['nullable', 'numeric', 'min:0'],
            'use_wallet' => ['sometimes', 'boolean'],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'cash_method' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->activate($customer, [
            'line_charge' => (float) ($validated['line_charge'] ?? 0),
            'device_id' => $validated['device_id'] ?? null,
            'device_charge' => (float) ($validated['device_charge'] ?? 0),
            'use_wallet' => (bool) ($validated['use_wallet'] ?? true),
            'cash_amount' => (float) ($validated['cash_amount'] ?? 0),
            'cash_method' => (string) ($validated['cash_method'] ?? 'cash'),
            'notes' => $validated['notes'] ?? null,
        ]);

        $activation = $result['activation'];

        return response()->json([
            'message' => $result['message'],
            'activation' => [
                'id' => $activation->id,
                'line_charge' => (float) $activation->line_charge,
                'device_charge' => (float) $activation->device_charge,
                'total_charged' => (float) $activation->total_charged,
                'wallet_applied' => (float) $activation->wallet_applied,
                'cash_collected' => (float) $activation->cash_collected,
                'remaining_due' => (float) ($result['remaining_due'] ?? 0),
                'device_id' => $activation->device_id,
                'invoice_id' => $activation->invoice_id,
            ],
            'wallet_balance' => (float) $customer->fresh()->account_balance,
        ]);
    }
}
