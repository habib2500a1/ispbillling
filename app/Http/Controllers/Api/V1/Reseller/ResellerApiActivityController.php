<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerPortalActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $rows = ResellerPortalActivityLog::query()
            ->where('reseller_id', $reseller->id)
            ->with(['staff:id,name,login'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest('created_at')
            ->paginate(min(50, (int) $request->query('per_page', 30)));

        return response()->json($rows);
    }
}
