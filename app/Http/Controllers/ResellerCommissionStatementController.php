<?php

namespace App\Http\Controllers;

use App\Models\ResellerCommission;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ResellerCommissionStatementController extends Controller
{
    public function show(ResellerCommission $commission): Response
    {
        abort_unless(Auth::guard('web')->check(), 401);

        $commission->load(['reseller', 'customer', 'payment']);

        $html = view('payments.reseller-commission-statement', [
            'commission' => $commission,
            'company' => config('isp.company_name', 'ISP'),
        ])->render();

        $tmpDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $tmpDir]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="commission-'.$commission->id.'.pdf"',
        ]);
    }
}
