<?php

namespace App\Http\Controllers;

use App\Services\Saas\SaasBillingService;
use App\Services\Saas\SaasContext;

class SaasLockedController extends Controller
{
    public function __invoke()
    {
        $operator = SaasContext::operator();
        if ($operator) {
            app(SaasBillingService::class)->refreshLock($operator);
            $operator->refresh();
        }

        if (! $operator || ! $operator->isAccessBlocked()) {
            return redirect()->route('dashboard');
        }

        $invoice = $operator->invoices()->where('status', '!=', 'paid')->latest()->first();

        return view('saas-locked', compact('operator', 'invoice'));
    }
}
