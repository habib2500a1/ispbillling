<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('portal.payments.index', [
            'payments' => $payments,
        ]);
    }
}
