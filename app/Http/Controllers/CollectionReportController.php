<?php

namespace App\Http\Controllers;

use App\Models\CollectionSummary;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CollectionReportController extends Controller
{
    public function index(Request $request)
    {
        if (! hasAccess(['Super Admin', 'Operator'], ['payment-collection-report', 'payment-collection', 'amount-collection-report'])) {
            abort(403, 'Unauthorized action.');
        }

        $from = $this->dateOrDefault($request->input('fromDate'), now()->startOfMonth()->toDateString());
        $to = $this->dateOrDefault($request->input('toDate'), now()->toDateString());
        [$fromAt, $toAt] = $this->collectionWindow($from, $to);
        $seeAll = canReviewAllCollections();
        $collector = $seeAll ? trim((string) $request->input('collector', '')) : (string) auth()->user()->email;

        $with = ['customer', 'customer.pppUser'];
        if (Schema::hasTable('customers_addresses')) {
            $with[] = 'customerAddresses';
        }

        $query = CollectionSummary::query()
            ->with($with)
            ->whereBetween('collection_date', [$fromAt, $toAt]);

        $this->constrainToViewer($query, $seeAll, $collector);

        if ($request->ajax()) {
            $total = (float) (clone $query)->sum('collection_amount');
            $count = (int) (clone $query)->count();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_name', function ($row) {
                    return $row->customer->customer_name ?? 'N/A';
                })
                ->addColumn('customers_address', function ($row) {
                    $formattedAddresses = [];
                    foreach ($row->customerAddresses ?? [] as $address) {
                        $addressParts = array_filter([
                            $address->input_type_text,
                            $address->input_type_dropdown,
                            $address->input_type_textarea,
                        ]);
                        if ($addressParts !== []) {
                            $formattedAddresses[] = implode(', ', $addressParts);
                        }
                    }

                    return implode('; ', $formattedAddresses);
                })
                ->addColumn('ppp_secret', function ($row) {
                    return $row->customer?->pppUser?->username ?? 'N/A';
                })
                ->editColumn('collection_date', function ($row) {
                    return $row->collection_date
                        ? Carbon::parse($row->collection_date)->format('d M Y H:i')
                        : '—';
                })
                ->editColumn('collection_amount', function ($row) {
                    return number_format((float) $row->collection_amount, 2);
                })
                ->editColumn('collected_by', function ($row) {
                    return collectorDisplayName($row->collected_by);
                })
                ->with([
                    'summary' => [
                        'from' => $from,
                        'to' => $to,
                        'total' => $total,
                        'count' => $count,
                        'avg' => $count > 0 ? round($total / $count, 2) : 0,
                    ],
                ])
                ->rawColumns(['customers_address', 'ppp_secret'])
                ->make(true);
        }

        $collectors = $this->collectorsForViewer($seeAll);

        $fundFlow = [
            'from' => $from,
            'to' => $to,
            'total' => (float) (clone $query)->sum('collection_amount'),
            'count' => (int) (clone $query)->count(),
            'avg' => 0.0,
        ];
        if ($fundFlow['count'] > 0) {
            $fundFlow['avg'] = round($fundFlow['total'] / $fundFlow['count'], 2);
        }

        return view('reports.collections.index', [
            'collectors' => $collectors,
            'fundFlow' => $fundFlow,
            'from' => $from,
            'to' => $to,
            'canReviewAll' => $seeAll,
            'viewerName' => auth()->user()->name,
        ]);
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(string $id) {}

    public function edit(string $id) {}

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}

    private function constrainToViewer($query, bool $seeAll, string $collector): void
    {
        if (! $seeAll) {
            $user = auth()->user();
            $query->where(function ($q) use ($user) {
                $q->where('collected_by', $user->email)
                    ->orWhere('collected_by', $user->name);
            });

            return;
        }

        if ($collector !== '') {
            $query->whereIn('collected_by', collectionCollectorAliases($collector));
        }
    }

    private function collectorsForViewer(bool $seeAll)
    {
        return collectionCollectorChoices($seeAll);
    }

    private function dateOrDefault(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $fallback;
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function collectionWindow(string $from, string $to): array
    {
        return [
            Carbon::parse($from)->startOfDay()->toDateTimeString(),
            Carbon::parse($to)->endOfDay()->toDateTimeString(),
        ];
    }
}
