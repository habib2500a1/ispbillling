<?php

namespace App\Http\Controllers;

use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionPdfService;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ResellerCommissionStatementController extends Controller
{
    public function show(ResellerCommission $commission, ResellerCommissionPdfService $pdf): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof \App\Models\User, 401);
        abort_unless(
            StaffCapability::for($user)->canBilling() || StaffCapability::for($user)->canReports(),
            403,
            'Billing or reports access required.',
        );

        $commission->loadMissing('reseller');
        if ($user->tenant_id !== null) {
            abort_unless((int) $commission->reseller?->tenant_id === (int) $user->tenant_id, 403);
        }

        return $pdf->singleCommissionResponse($commission->reseller, $commission, inline: true);
    }
}
