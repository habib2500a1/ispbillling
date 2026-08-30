<?php

namespace App\Http\Controllers;

use App\Models\CollectionSummary;
use App\Models\CustomersAddress;
use App\Models\User;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CollectionReportController extends Controller
{
    public function __construct()
    {
        if (! auth()->user()?->can('payment-collection-report')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $from = $this->dateOrDefault($request->input('fromDate'), now()->startOfMonth()->toDateString());
        $to = $this->dateOrDefault($request->input('toDate'), now()->toDateString());
        [$fromAt, $toAt] = $this->collectionWindow($from, $to);
        $collector = trim((string) $request->input('collector', ''));

        $query = CollectionSummary::query()
            ->with(['customer', 'customer.pppUser'])
            ->whereBetween('collection_date', [$fromAt, $toAt]);

        if ($collector !== '') {
            $query->where('collected_by', $collector);
        }

        if ($request->ajax()) {
            $total = (float) (clone $query)->sum('collection_amount');
            $count = (int) (clone $query)->count();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_name', function ($row) {
                    return $row->customer->customer_name ?? 'N/A';
                })
                ->addColumn('customers_address', function ($row) {
                    if (! Schema::hasTable('customers_addresses')) {
                        return '';
                    }

                    $addresses = CustomersAddress::select('input_type_text', 'input_type_dropdown', 'input_type_textarea')
                        ->where('customer_address_unique_id', $row->customer_collection_unique_id)
                        ->get();

                    $formattedAddresses = [];
                    foreach ($addresses as $address) {
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

        $collectors = User::select('name', 'email')->orderBy('name')->get();

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

        return view('reports.collections.index', compact('collectors', 'fundFlow', 'from', 'to'));
    }

    public function create() {}

    public function store(Request $request) {}

    public function show(string $id) {}

    public function edit(string $id) {}

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}

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
