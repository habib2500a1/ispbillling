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

        $paymentQuery = Payment::query()
            ->where('customer_id', $customer->id);

        $payments = (clone $paymentQuery)
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20);

        $completed = (clone $paymentQuery)->where('status', 'completed')->get();
        $completedPayments = $completed->filter(fn (Payment $payment) => ! $payment->isRefund());
        $refundPayments = $completed->filter(fn (Payment $payment) => $payment->isRefund());

        return view('portal.payments.index', [
            'payments' => $payments,
            'completedCount' => $completedPayments->count(),
            'completedTotal' => round((float) $completedPayments->sum('amount'), 2),
            'refundTotal' => round((float) $refundPayments->sum('amount'), 2),
            'lastPaidAt' => $completedPayments->sortByDesc('paid_at')->first()?->paid_at,
        ]);
    }
}
