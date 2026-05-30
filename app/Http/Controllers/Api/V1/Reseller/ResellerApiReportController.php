<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Resellers\ResellerReportExportService;
use App\Services\Resellers\ResellerReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResellerApiReportController extends Controller
{
    public function summary(Request $request, ResellerReportService $reports): JsonResponse
    {
        $reseller = $request->user();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $customerIds = $reseller->customers()->pluck('id');

        $collectionTotal = $customerIds->isEmpty() ? 0.0 : (float) Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        $dueTotal = $customerIds->isEmpty() ? 0.0 : (float) Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['open', 'partial'])
            ->sum(DB::raw('GREATEST(0, total - amount_paid)'));

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'collection_total' => round($collectionTotal, 2),
            'due_total' => round($dueTotal, 2),
            'commission' => $reports->summary($from, $to, $reseller->id, $reseller->tenant_id),
            'clients' => [
                'total' => $reseller->customers()->count(),
                'active' => $reseller->customers()->where('status', 'active')->count(),
            ],
        ]);
    }

    public function export(Request $request, ResellerReportExportService $exports): StreamedResponse|Response|JsonResponse
    {
        $reseller = $request->user();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $type = (string) $request->input('type', 'collection');
        $format = strtolower((string) $request->input('format', 'csv'));

        if (! in_array($type, ['collection', 'commission', 'wallet', 'due', 'clients'], true)) {
            return response()->json(['message' => 'Invalid report type.'], 422);
        }

        if ($request->boolean('json')) {
            $dataset = $exports->dataset($reseller, $type, $from, $to);

            return response()->json([
                'type' => $type,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'headers' => $dataset['headers'],
                'rows' => $dataset['rows'],
            ]);
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
