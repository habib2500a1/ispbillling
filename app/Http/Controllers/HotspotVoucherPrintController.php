<?php

namespace App\Http\Controllers;

use App\Models\HotspotVoucher;
use App\Support\CompanyBranding;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class HotspotVoucherPrintController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof \App\Models\User, 401);
        abort_unless(
            StaffCapability::for($user)->canNetwork() || StaffCapability::for($user)->canBilling(),
            403,
            'Network or billing access required to print vouchers.',
        );

        $tenantId = TenantResolver::requiredTenantId();
        $query = HotspotVoucher::query()->where('tenant_id', $tenantId);

        if ($request->filled('batch')) {
            $query->where('batch_name', $request->string('batch')->toString());
        }

        if ($request->filled('ids')) {
            $ids = array_filter(array_map('intval', explode(',', (string) $request->input('ids'))));
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        }

        $vouchers = $query
            ->orderBy('batch_name')
            ->orderBy('code')
            ->limit(500)
            ->get();

        abort_if($vouchers->isEmpty(), 404, 'No vouchers to print.');

        $html = view('hotspot.vouchers-pdf', [
            'vouchers' => $vouchers,
            'company' => CompanyBranding::name(),
            'printedAt' => now()->format('d M Y H:i'),
        ])->render();

        $tmpDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmpDir,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->WriteHTML($html);

        $batch = $request->string('batch')->toString();
        $filename = $batch !== ''
            ? 'hotspot-vouchers-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $batch).'.pdf'
            : 'hotspot-vouchers.pdf';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
