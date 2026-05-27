<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Payments\PublicPaymentOrchestrator;
use App\Support\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalPrepayController extends Controller
{
    public function store(Request $request, CustomerPrepayService $prepay, PublicPaymentOrchestrator $payments): RedirectResponse
    {
        $customer = $request->user('customer');
        $maxMonths = $prepay->maxMonths();

        $validated = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:'.$maxMonths],
            'gateway' => ['required', 'string', 'in:'.implode(',', [
                PaymentGateway::BKASH,
                PaymentGateway::SSLCOMMERZ,
                PaymentGateway::NAGAD,
                PaymentGateway::ROCKET,
                PaymentGateway::PIPRAPAY,
            ])],
        ]);

        $quote = $prepay->assertQuote($customer, (int) $validated['months']);

        return $payments->startPrepayPayment(
            $customer,
            (float) $quote['total_amount'],
            (int) $quote['months'],
            $validated['gateway'],
            'portal',
        );
    }
}
