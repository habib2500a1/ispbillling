<?php

namespace App\Http\Controllers;

use App\Services\Billing\CustomerSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        if (! hasAccess(['Super Admin', 'Operator', 'Reseller'], ['all-customer', 'all-subscribers', 'new-customer'])) {
            return response()->json(['results' => []]);
        }

        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'results' => app(CustomerSearch::class)->suggest($q, 10),
            'list_url' => route('customers.index', ['q' => $q]),
        ]);
    }
}
