<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionPdfService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResellerApiCommissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $rows = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->with(['customer:id,name,customer_code', 'payment:id,amount,paid_at'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('earned_at')
            ->paginate(min(50, (int) $request->query('per_page', 20)));

        return response()->json($rows);
    }

    public function statement(Request $request, ResellerCommissionPdfService $pdf): Response
    {
        $reseller = $request->user();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $status = $request->filled('status') ? (string) $request->input('status') : null;

        if ($status !== null && ! in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            abort(422, 'Invalid status filter.');
        }

        return $pdf->periodStatementResponse($reseller, $from, $to, $status, inline: false);
    }

    public function showStatement(ResellerCommission $commission, ResellerCommissionPdfService $pdf, Request $request): Response
    {
        return $pdf->singleCommissionResponse($request->user(), $commission, inline: true);
    }
}
