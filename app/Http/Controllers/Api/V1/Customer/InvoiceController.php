<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->paginate(20);

        return response()->json([
            'data' => collect($invoices->items())->map(fn (Invoice $inv) => $mobile->invoiceSummary($inv)),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        return response()->json($mobile->invoiceDetail($customer, $invoice));
    }
}
