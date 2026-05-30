<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerInvoiceNotifyService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResellerInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $reseller = auth('reseller')->user();
        $customerIds = $reseller->customers()->pluck('id');

        $invoices = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['customer:id,name,customer_code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('issue_date')
            ->paginate(20)
            ->withQueryString();

        return view('reseller.invoices.index', [
            'reseller' => $reseller,
            'invoices' => $invoices,
        ]);
    }

    public function show(Invoice $invoice, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $invoice->load(['customer', 'items']);
        $customers->assertOwned($reseller, $invoice->customer);

        $notify = app(ResellerInvoiceNotifyService::class);
        $channels = $invoice->customer
            ? $notify->availableChannels($invoice->customer)
            : ['sms' => false, 'email' => false];

        return view('reseller.invoices.show', [
            'reseller' => $reseller,
            'invoice' => $invoice,
            'notifyChannels' => $channels,
        ]);
    }

    public function generate(Request $request, Customer $customer, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_GENERATE)) {
            throw ValidationException::withMessages(['permission' => 'Invoice generation is not allowed.']);
        }

        $customers->assertOwned($reseller, $customer);
        $customer->load('package');

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::today(), false, null);
        if ($invoice === null) {
            return back()->withErrors(['invoice' => 'Could not generate invoice — may already exist for this period or auto-invoice is off.']);
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.generate', $invoice);

        return redirect()
            ->route('reseller.invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->invoice_number.' generated.');
    }

    public function send(
        Request $request,
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerInvoiceNotifyService $notify,
    ): RedirectResponse {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::BILLING_VIEW)) {
            throw ValidationException::withMessages(['permission' => 'Sending invoices is not allowed.']);
        }

        $invoice->load('customer');
        $customers->assertOwned($reseller, $invoice->customer);

        $validated = $request->validate([
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', 'in:sms,email'],
            'include_payment_link' => ['nullable', 'boolean'],
        ]);

        $result = $notify->send(
            $invoice,
            $reseller,
            $validated['channels'],
            (bool) ($validated['include_payment_link'] ?? true),
        );

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.send', $invoice, [
            'sms' => $result['sms'],
            'email' => $result['email'],
        ]);

        $parts = array_filter([
            $result['sms'] ? 'SMS' : null,
            $result['email'] ? 'email' : null,
        ]);

        return back()->with('status', 'Invoice sent via '.implode(' and ', $parts).'.');
    }
}
