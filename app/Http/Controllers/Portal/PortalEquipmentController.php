<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PortalEquipmentController extends Controller
{
    public function index(): View
    {
        $customer = auth('customer')->user();
        $devices = $customer->devices()
            ->with(['olt', 'oltPort', 'onuHealthScore'])
            ->where('type', '!=', 'olt')
            ->orderByRaw("CASE type WHEN 'onu' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $olts = $devices->pluck('olt')->filter()->unique('id')->values();

        return view('portal.equipment', [
            'customer' => $customer,
            'devices' => $devices,
            'olts' => $olts,
        ]);
    }
}
