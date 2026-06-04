<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use Illuminate\View\View;

class ResellerSubResellerController extends Controller
{
    public function index(): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $partners = $reseller->children()
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        return view('reseller.sub-resellers.index', [
            'reseller' => $reseller,
            'partners' => $partners,
        ]);
    }

    public function show(Reseller $child): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless((int) $child->parent_id === (int) $reseller->id, 404);

        $child->loadCount('customers');

        return view('reseller.sub-resellers.show', [
            'reseller' => $reseller,
            'partner' => $child,
            'stats' => $child->dashboardStats(),
        ]);
    }
}
