<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOnuController extends Controller
{
    public function show(Request $request, int $customer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $model = Customer::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($customer)
            ->firstOrFail();

        $meta = is_array($model->meta) ? $model->meta : [];

        return response()->json([
            'customer_id' => $model->id,
            'onu_mac' => $meta['onu_mac'] ?? null,
            'mac_binding' => $meta['mac_binding'] ?? null,
            'epon_port' => $meta['epon_port'] ?? null,
        ]);
    }

    public function update(Request $request, int $customer): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $model = Customer::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($customer)
            ->firstOrFail();

        $data = $request->validate([
            'onu_mac' => ['nullable', 'string', 'max:64'],
            'mac_binding' => ['nullable', 'string', 'max:64'],
            'epon_port' => ['nullable', 'string', 'max:64'],
        ]);

        $meta = is_array($model->meta) ? $model->meta : [];
        foreach (['onu_mac', 'mac_binding', 'epon_port'] as $key) {
            if (array_key_exists($key, $data)) {
                $meta[$key] = $data[$key];
            }
        }

        $model->update(['meta' => $meta]);

        return response()->json(['message' => 'ONU binding updated.', 'meta' => [
            'onu_mac' => $meta['onu_mac'] ?? null,
            'mac_binding' => $meta['mac_binding'] ?? null,
            'epon_port' => $meta['epon_port'] ?? null,
        ]]);
    }
}
