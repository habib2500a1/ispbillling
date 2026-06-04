<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerApiKey;
use App\Services\Resellers\ResellerApiKeyService;
use App\Support\ResellerPortalPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResellerApiKeyManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();
        abort_unless($reseller->api_access_enabled, 403, 'API access is disabled for this account.');

        $keys = $reseller->apiKeys()
            ->orderByDesc('id')
            ->get(['id', 'name', 'key_prefix', 'abilities', 'is_active', 'last_used_at', 'expires_at', 'rate_limit_per_minute', 'created_at']);

        return response()->json(['keys' => $keys]);
    }

    public function store(Request $request, ResellerApiKeyService $service): JsonResponse
    {
        $reseller = $request->user();
        abort_unless($reseller->api_access_enabled, 403, 'API access is disabled for this account.');

        $allowed = $reseller->portalPermissions();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in($allowed)],
        ]);

        $abilities = isset($validated['abilities'])
            ? array_values(array_intersect($validated['abilities'], $allowed))
            : null;

        $result = $service->create($reseller, $validated['name'], $abilities !== [] ? $abilities : null);

        return response()->json([
            'key' => [
                'id' => $result['model']->id,
                'name' => $result['model']->name,
                'key_prefix' => $result['model']->key_prefix,
                'plain_key' => $result['plain'],
            ],
            'message' => 'API key created. Store plain_key now — it is not shown again.',
        ], 201);
    }

    public function destroy(Request $request, ResellerApiKey $apiKey, ResellerApiKeyService $service): JsonResponse
    {
        $reseller = $request->user();
        abort_unless((int) $apiKey->reseller_id === (int) $reseller->id, 404);

        $service->revoke($apiKey);

        return response()->json(['message' => 'API key revoked.']);
    }
}
