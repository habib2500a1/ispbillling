<?php

namespace App\Http\Controllers;

use App\Services\Billing\PublicPayCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPayController extends Controller
{
    public function lookup(): View
    {
        return view('pay.lookup');
    }

    public function find(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lookup' => 'required|string|max:64',
        ]);

        $customer = PublicPayCustomer::findByLookup($data['lookup']);
        if (! $customer) {
            return back()->withInput()->withErrors([
                'lookup' => __('User ID / PPPoE / mobile not found.'),
            ]);
        }

        PublicPayCustomer::remember($customer);

        return redirect()->route('pay.show', $customer->customer_unique_id);
    }

    public function show(string $ref): View|RedirectResponse
    {
        $customer = PublicPayCustomer::findByLookup($ref);
        if (! $customer) {
            return redirect()->route('pay.lookup')->withErrors([
                'lookup' => __('User ID not found.'),
            ]);
        }

        PublicPayCustomer::remember($customer);

        $billing = $customer->billing;
        $due = max(0, (float) ($billing?->due_amount ?? 0));
        $rent = (float) ($billing?->monthly_rent ?? 0);
        $amount = $due > 0 ? $due : $rent;
        $onu = $customer->primaryOnu();

        return view('pay.show', [
            'customer' => $customer,
            'billing' => $billing,
            'due' => $due,
            'rent' => $rent,
            'amount' => $amount,
            'onu' => $onu,
            'gateways' => [
                'bkash' => (bool) siteUrlSettings('payment_bkash_enabled'),
                'nagad' => (bool) siteUrlSettings('payment_nagad_enabled'),
                'sslcommerz' => (bool) siteUrlSettings('payment_sslcommerz_enabled'),
            ],
        ]);
    }

    public function checkout(Request $request, string $ref): RedirectResponse
    {
        $customer = PublicPayCustomer::findByLookup($ref);
        if (! $customer) {
            return redirect()->route('pay.lookup')->withErrors([
                'lookup' => __('User ID not found.'),
            ]);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:200000',
            'gateway' => 'required|in:bkash,nagad,sslcommerz',
        ]);

        PublicPayCustomer::remember($customer);
        session(['public_pay_amount' => (float) $data['amount']]);

        $route = match ($data['gateway']) {
            'bkash' => 'pay.start.bkash',
            'nagad' => 'pay.start.nagad',
            default => 'pay.start.sslcommerz',
        };

        return redirect()->route($route, ['amount' => $data['amount']]);
    }
}
