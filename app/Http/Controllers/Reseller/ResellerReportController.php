<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Resellers\ResellerReportExportService;
use App\Services\Resellers\ResellerReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResellerReportController extends Controller
{
    public function index(Request $request, ResellerReportService $reports): View
    {
        $reseller = auth('reseller')->user();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        $customerIds = $reseller->customers()->pluck('id');

        $collectionTotal = $customerIds->isEmpty() ? 0.0 : (float) Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $commissionSummary = $reports->summary($from, $to, $reseller->id, $reseller->tenant_id);

        $dueTotal = $customerIds->isEmpty() ? 0.0 : (float) Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['open', 'partial'])
            ->sum(\Illuminate\Support\Facades\DB::raw('GREATEST(0, total - amount_paid)'));

        return view('reseller.reports.index', [
            'reseller' => $reseller,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'collectionTotal' => $collectionTotal,
            'commissionSummary' => $commissionSummary,
            'dueTotal' => $dueTotal,
            'clientCount' => $reseller->customers()->count(),
            'activeCount' => $reseller->customers()->where('status', 'active')->count(),
        ]);
    }

    public function export(Request $request, ResellerReportExportService $exports): StreamedResponse|Response
    {
        $reseller = auth('reseller')->user();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $type = (string) $request->input('type', 'collection');
        $format = strtolower((string) $request->input('format', 'csv'));

        if (! in_array($type, ['collection', 'commission', 'wallet', 'due', 'clients'], true)) {
            abort(422, 'Invalid report type.');
        }

        if ($format === 'xlsx') {
            $binary = $exports->xlsxBinary($reseller, $type, $from, $to);
            $filename = $exports->filename($reseller, $type, $from, $to, 'xlsx');

            return new Response($binary, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return $exports->streamedCsv($reseller, $type, $from, $to);
    }
}
