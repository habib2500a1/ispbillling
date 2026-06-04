<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Resellers\ResellerCustomerBillingEngine;
use App\Services\Resellers\ResellerCustomerDueReminderService;
use App\Services\Resellers\ResellerBulkInvoiceService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerInvoiceAdjustmentService;
use App\Services\Resellers\ResellerInvoiceLineService;
use App\Services\Resellers\ResellerInvoiceNotifyService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Services\Resellers\ResellerWholesaleDebitService;
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

        $bulk = app(ResellerBulkInvoiceService::class);

        return view('reseller.invoices.index', [
            'reseller' => $reseller,
            'invoices' => $invoices,
            'eligibleSubscribers' => $bulk->countEligible($reseller),
            'bulkGenerateEnabled' => (bool) config('reseller_billing.portal_bulk_invoice_generate', true),
        ]);
    }

    public function bulkGenerate(Request $request, ResellerBulkInvoiceService $bulk): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_GENERATE)) {
            throw ValidationException::withMessages(['permission' => 'Invoice generation is not allowed.']);
        }

        $validated = $request->validate([
            'reference_date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['reference_date'])
            ? Carbon::parse($validated['reference_date'])->startOfDay()
            : Carbon::today();

        $result = $bulk->generateForReseller($reseller, $date, false);

        $message = sprintf(
            'Monthly bills: %d created, %d skipped (already billed or not billable).',
            $result['created'],
            $result['skipped'],
        );

        if ($result['errors'] !== []) {
            $message .= ' Errors: '.implode('; ', array_slice($result['errors'], 0, 3));
            if (count($result['errors']) > 3) {
                $message .= ' … +'.(count($result['errors']) - 3).' more';
            }
        }

        return redirect()
            ->route('reseller.invoices.index')
            ->with('status', $message);
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

        try {
            $noProrate = app(ResellerCustomerBillingEngine::class)->shouldSkipProration($customer);
            $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::today(), $noProrate, null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        if ($invoice === null) {
            return back()->withErrors(['invoice' => 'Could not generate invoice — may already exist for this period or auto-invoice is off.']);
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.generate', $invoice);

        $status = 'Invoice '.$invoice->invoice_number.' generated.';
        $wholesaleNote = app(ResellerWholesaleDebitService::class)->messageForInvoice($invoice);
        if ($wholesaleNote !== '') {
            $status .= ' '.$wholesaleNote;
        }

        return redirect()
            ->route('reseller.invoices.show', $invoice)
            ->with('status', $status);
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

    public function adjust(
        Request $request,
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerInvoiceAdjustmentService $adjustments,
    ): RedirectResponse {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_ADJUST)) {
            throw ValidationException::withMessages(['permission' => 'Invoice adjustment is not allowed.']);
        }

        $invoice->load('customer');
        $customers->assertOwned($reseller, $invoice->customer);

        $validated = $request->validate([
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'waive_full' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $waive = $request->boolean('waive_full');
        $amount = $waive ? 0.0 : (float) ($validated['discount_amount'] ?? 0);

        if (! $waive && $amount <= 0) {
            return back()->withErrors(['discount_amount' => 'Enter a discount amount or choose full waive.']);
        }

        try {
            $result = $adjustments->applyAdjustment(
                $reseller,
                $invoice,
                $amount,
                $validated['reason'] ?? null,
                $waive,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', sprintf(
            'Invoice adjusted. Discount now %s BDT.',
            number_format($result['new_discount'], 2),
        ));
    }

    public function sendDueReminder(
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerCustomerDueReminderService $reminders,
    ): RedirectResponse {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::BILLING_VIEW)) {
            throw ValidationException::withMessages(['permission' => 'Not allowed.']);
        }

        $invoice->load('customer');
        $customers->assertOwned($reseller, $invoice->customer);

        if (! $reminders->sendForInvoice($invoice, $reseller)) {
            return back()->withErrors(['reminder' => 'Could not send reminder (no balance, already sent today, or SMS disabled).']);
        }

        return back()->with('status', 'Due reminder sent to subscriber.');
    }

    public function updateLine(
        Request $request,
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerInvoiceLineService $lines,
    ): RedirectResponse {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Invoice editing is not allowed.']);
        }

        $invoice->load('customer');
        $customers->assertOwned($reseller, $invoice->customer);

        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $invoice->items()->findOrFail($validated['item_id']);
        $lines->updateLine(
            $reseller,
            $invoice,
            $item,
            (float) $validated['unit_price'],
            $validated['description'] ?? null,
        );

        return back()->with('status', 'Invoice line updated.');
    }

    public function addLine(
        Request $request,
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerInvoiceLineService $lines,
    ): RedirectResponse {
        $reseller = auth('reseller')->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Not allowed.']);
        }

        $invoice->load('customer');
        $customers->assertOwned($reseller, $invoice->customer);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
        ]);

        $lines->addAdjustmentLine(
            $reseller,
            $invoice,
            $validated['description'],
            (float) $validated['amount'],
        );

        return back()->with('status', 'Adjustment line added.');
    }
}
