<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerNetworkSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCustomerManageController extends Controller
{
    public function create(ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();

        return view('reseller.customers-create', [
            'reseller' => $reseller,
            'options' => $customers->formOptions($reseller),
        ]);
    }

    public function store(Request $request, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $customers->create($reseller, $request);
        $message = 'Subscriber created. '.($result['billing']['message'] ?? '');

        if (! empty($result['billing']['payment'])) {
            return redirect()
                ->route('reseller.payments.receipt', $result['billing']['payment'])
                ->with('status', $message);
        }

        return redirect()
            ->route('reseller.customers.show', $result['customer'])
            ->with('status', $message);
    }

    public function show(Customer $customer, ResellerCustomerService $customers, ResellerNetworkSessionService $networkSessions): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);
        $customer->load(['package:id,name', 'zone:id,name', 'area:id,name']);

        $payments = $customer->payments()
            ->where('status', 'completed')
            ->latest('paid_at')
            ->limit(15)
            ->get();

        $pauseBilling = app(\App\Services\Resellers\ResellerSuspendedBillingService::class);
        $billingPaused = $pauseBilling->isBillingPaused($customer);
        $suspensionMonthCurrent = $billingPaused && $pauseBilling->isSuspensionMonthStillCurrent($customer);

        $invoices = $billingPaused
            ? $pauseBilling->invoicesQueryWhileSuspended($customer)->latest('issue_date')->limit(15)->get()
            : $customer->invoices()->latest('issue_date')->limit(15)->get();

        $networkSession = $networkSessions->liveDetail($customer);

        return view('reseller.customers-show', [
            'reseller' => $reseller,
            'customer' => $customer,
            'billingPaused' => $billingPaused,
            'suspensionMonthCurrent' => $suspensionMonthCurrent,
            'displayDue' => $pauseBilling->displayableOpenDue($customer),
            'payments' => $payments,
            'invoices' => $invoices,
            'networkSession' => $networkSession,
        ]);
    }

    public function edit(Customer $customer, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        return view('reseller.customers-edit', [
            'reseller' => $reseller,
            'customer' => $customer,
            'options' => $customers->formOptions($reseller),
        ]);
    }

    public function update(Request $request, Customer $customer, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $activating = $customers->isActivating($customer, $request);
        $customers->update($reseller, $customer, $request);

        $status = 'Subscriber updated.';
        if ($activating) {
            $pause = app(\App\Services\Resellers\ResellerSuspendedBillingService::class);
            $voided = $pause->voidStaleOpenInvoicesBeforeActivation($customer->fresh());
            $pause->clearPauseState($customer->fresh());
            if ($voided > 0) {
                $status .= " {$voided} old bill(s) cleared.";
            }
        }
        if ($activating && $request->boolean('generate_bill_on_activate')) {
            $bill = $customers->generateBillOnActivate($reseller, $customer->fresh());
            if ($bill['message'] !== '') {
                $status .= ' '.$bill['message'];
            }
        }

        return redirect()
            ->route('reseller.customers.show', $customer)
            ->with('status', $status);
    }
}
