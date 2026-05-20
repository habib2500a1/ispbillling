<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\MobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(Request $request, MobileSyncService $sync): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'max:50'],
            'items.*.action' => ['required', 'string', 'max:64'],
            'items.*.payload' => ['required', 'array'],
            'items.*.idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        return response()->json($sync->processBatch($user, $data['device_uuid'], $data['items']));
    }
}
