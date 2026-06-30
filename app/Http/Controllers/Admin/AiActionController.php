<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiActionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiActionController extends Controller
{
    public function approve(int $action, AiActionApprovalService $actions): JsonResponse
    {
        $this->authorizeAdmin();

        $request = $actions->approve($action, auth()->user());

        return response()->json([
            'id' => $request->id,
            'status' => $request->status,
            'summary' => $request->summary,
            'executed_at' => $request->executed_at?->toIso8601String(),
        ]);
    }

    public function reject(Request $request, int $action, AiActionApprovalService $actions): JsonResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $actionRequest = $actions->reject($action, auth()->user(), $data['reason'] ?? null);

        return response()->json([
            'id' => $actionRequest->id,
            'status' => $actionRequest->status,
            'summary' => $actionRequest->summary,
            'rejection_reason' => $actionRequest->rejection_reason,
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager']), 403);
    }
}
