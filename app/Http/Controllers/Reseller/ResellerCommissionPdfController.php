<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ResellerCommissionPdfController extends Controller
{
    public function statement(Request $request, ResellerCommissionPdfService $pdf): Response
    {
        $reseller = $this->resolveReseller();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $status = $request->filled('status') ? (string) $request->input('status') : null;

        if ($status !== null && ! in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            abort(422, 'Invalid status filter.');
        }

        return $pdf->periodStatementResponse($reseller, $from, $to, $status, inline: false);
    }

    public function show(ResellerCommission $commission, ResellerCommissionPdfService $pdf): Response
    {
        return $pdf->singleCommissionResponse($this->resolveReseller(), $commission, inline: true);
    }

    private function resolveReseller(): Reseller
    {
        $sanctum = Auth::guard('sanctum')->user();
        if ($sanctum instanceof Reseller) {
            return $sanctum;
        }

        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return $reseller;
    }
}
