<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerPortalActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerActivityController extends Controller
{
    public function index(Request $request): View
    {
        $reseller = auth('reseller')->user();

        $logs = ResellerPortalActivityLog::query()
            ->where('reseller_id', $reseller->id)
            ->with(['staff:id,name,login'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('reseller.activity.index', [
            'reseller' => $reseller,
            'logs' => $logs,
            'actionOptions' => \App\Support\ResellerPortalActivityLabels::actions(),
        ]);
    }
}
