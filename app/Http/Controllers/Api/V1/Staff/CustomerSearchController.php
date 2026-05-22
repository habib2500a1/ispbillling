<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\BillCollectionSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bill collection search for staff mobile (broader than Sanctum collector token alone).
 */
class CustomerSearchController extends Controller
{
    private const SEARCH_ROLES = [
        'super-admin',
        'isp-admin',
        'admin',
        'cashier',
        'collector',
        'branch-manager',
        'isp-manager',
        'isp-support',
        'isp-engineer',
    ];

    public function search(Request $request, BillCollectionSearchService $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(self::SEARCH_ROLES)) {
            return response()->json([
                'message' => 'Your account cannot search customers. Use Bill Collection in the admin panel.',
            ], 403);
        }

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $search->search($q)->take(20)->values(),
        ]);
    }
}
