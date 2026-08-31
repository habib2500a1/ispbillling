<?php

namespace App\Http\Controllers;

use App\Models\CollectionSummary;
use Illuminate\View\View;

class CollectionInvoiceController extends Controller
{
    public function show(int $id): View
    {
        return $this->receipt($id, false);
    }

    public function download(int $id): View
    {
        return $this->receipt($id, true);
    }

    private function receipt(int $id, bool $autoPrint): View
    {
        if (! hasAccess(['Super Admin', 'Operator'], ['payment-collection-invoice', 'payment-collection', 'all-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $collection = CollectionSummary::query()
            ->with(['customer.billing', 'customer.pppUser', 'customer.customerAddress', 'customer.package', 'customer.onus'])
            ->findOrFail($id);

        $customer = $collection->customer;
        if (! $customer) {
            abort(404, 'Customer not found for this invoice.');
        }

        $invoiceNo = $collection->invoice_no
            ? (string) siteUrlSettings('site_invoice_prefix').$collection->invoice_no
            : (string) $collection->id;

        $address = $customer->customerAddress
            ->map(fn ($a) => trim(implode(', ', array_filter([
                $a->input_type_text,
                $a->input_type_dropdown,
                $a->input_type_textarea,
            ]))))
            ->filter()
            ->implode('; ');

        $billing = $customer->billing;

        return view('invoices.collection-receipt', [
            'collection' => $collection,
            'customer' => $customer,
            'billing' => $billing,
            'invoiceNo' => $invoiceNo,
            'address' => $address,
            'autoPrint' => $autoPrint,
            'currency' => siteUrlSettings('site_currency') ?: 'BDT',
        ]);
    }
}
