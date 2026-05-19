<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSmartSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSmartSearchService $search): JsonResponse
    {
        $q = (string) $request->query('q', '');

        return response()->json([
            'results' => $search->search($q),
        ]);
    }
}
