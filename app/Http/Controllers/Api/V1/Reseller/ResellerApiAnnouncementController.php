<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiAnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $items = ResellerAnnouncement::query()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('is_active', true)
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->filter(fn (ResellerAnnouncement $a) => $a->isVisibleTo($reseller))
            ->values()
            ->map(fn (ResellerAnnouncement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'priority' => $a->priority,
                'published_at' => $a->published_at?->toIso8601String(),
            ]);

        return response()->json(['announcements' => $items]);
    }
}
