<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerPortalNotification;
use App\Services\Resellers\ResellerPortalNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $rows = ResellerPortalNotification::query()
            ->where('reseller_id', $reseller->id)
            ->latest('id')
            ->paginate(min(30, (int) $request->query('per_page', 20)));

        return response()->json([
            'unread_count' => app(ResellerPortalNotifier::class)->unreadCount($reseller),
            'notifications' => $rows,
        ]);
    }

    public function markRead(Request $request, ResellerPortalNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->reseller_id === (int) $request->user()->id, 404);
        $notification->markRead();

        return response()->json(['message' => 'Marked read.']);
    }
}
