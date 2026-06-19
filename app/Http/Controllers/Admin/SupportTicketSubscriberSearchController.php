<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillCollectionSearchService;
use App\Support\SupportPanelAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Session-auth JSON search for support ticket create (bypasses Livewire morph issues). */
final class SupportTicketSubscriberSearchController extends Controller
{
    public function __invoke(Request $request, BillCollectionSearchService $search): JsonResponse
    {
        abort_unless(auth()->check(), 403);

        abort_unless(SupportPanelAccess::canSearchTicketSubscribers(auth()->user()), 403);

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        if (preg_match('/\(([^)]+)\)\s*$/u', $query, $m)) {
            $code = trim($m[1]);
            if (mb_strlen($code) >= 2) {
                $query = $code;
            }
        }

        return response()->json([
            'data' => $search->search($query, 25)->values(),
        ]);
    }
}
