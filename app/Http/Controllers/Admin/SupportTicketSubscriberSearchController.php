<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillCollectionSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Session-auth JSON search for support ticket create (bypasses Livewire morph issues). */
final class SupportTicketSubscriberSearchController extends Controller
{
    public function __invoke(Request $request, BillCollectionSearchService $search): JsonResponse
    {
        abort_unless(auth()->check(), 403);

        abort_unless(auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
            'isp-support',
            'isp-engineer',
            'admin',
            'branch-manager',
        ]) ?? false, 403);

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $search->search($query, 25)->values(),
        ]);
    }
}
