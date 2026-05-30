<?php

namespace App\Http\Controllers\Api\V1\Reseller;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResellerApiInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();
        $customerIds = $reseller->customers()->pluck('id');

        $rows = Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->with(['customer:id,name,customer_code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('issue_date')
            ->paginate(min(50, (int) $request->query('per_page', 20)));

        return response()->json($rows);
    }

    public function show(Request $request, Invoice $invoice, ResellerCustomerService $customers): JsonResponse
    {
        $customers->assertOwned($request->user(), $invoice->customer);

        return response()->json($invoice->load(['customer:id,name,customer_code', 'items']));
    }

    public function generate(Request $request, Customer $customer, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = $request->user();
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::INVOICE_GENERATE)) {
            throw ValidationException::withMessages(['permission' => 'Invoice generation is not allowed.']);
        }

        $customers->assertOwned($reseller, $customer);
        $customer->load('package');

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::today(), false, null);
        if ($invoice === null) {
            return response()->json(['message' => 'Could not generate invoice for this period.'], 422);
        }

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.generate', $invoice);

        return response()->json([
            'invoice' => $invoice->load('items'),
            'pdf_url' => url('/api/v1/reseller/invoices/'.$invoice->id.'/pdf'),
        ], 201);
    }

    public function send(
        Request $request,
        Invoice $invoice,
        ResellerCustomerService $customers,
        ResellerInvoiceNotifyService $notify,
    ): JsonResponse {
        $reseller = $request->user();
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

        return response()->json([
            'message' => 'Invoice sent.',
            'sent' => [
                'sms' => $result['sms'],
                'email' => $result['email'],
            ],
            'payment_url' => $result['payment_url'],
            'whatsapp_url' => $result['whatsapp_url'],
        ]);
    }

    public function notifyChannels(Request $request, Invoice $invoice, ResellerCustomerService $customers, ResellerInvoiceNotifyService $notify): JsonResponse
    {
        $invoice->load('customer');
        $customers->assertOwned($request->user(), $invoice->customer);

        return response()->json([
            'channels' => $invoice->customer
                ? $notify->availableChannels($invoice->customer)
                : ['sms' => false, 'email' => false],
        ]);
    }
}
