<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerOnuOpticalService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PortalOnuController extends Controller
{
    public function index(CustomerOnuOpticalService $onu): View
    {
        $customer = auth('customer')->user();
        $snapshot = $onu->snapshot($customer);
        $customer->loadMissing('devices');

        return view('portal.onu', [
            'customer' => $customer,
            'onu' => $snapshot,
        ]);
    }

    public function live(CustomerOnuOpticalService $onu): JsonResponse
    {
        $customer = auth('customer')->user();

        return response()->json($onu->snapshot($customer));
    }
}
