<?php

namespace App\Http\Controllers;

use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ResellerCommissionStatementController extends Controller
{
    public function show(ResellerCommission $commission, ResellerCommissionPdfService $pdf): Response
    {
        abort_unless(Auth::guard('web')->check(), 401);

        $commission->loadMissing('reseller');

        return $pdf->singleCommissionResponse($commission->reseller, $commission, inline: true);
    }
}
