<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Reseller;
use App\Models\User;
use App\Support\ResellerBranding;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoicePdfController extends Controller
{
    public function show(Invoice $invoice): Response
    {
        $webUser = Auth::guard('web')->user();
        $sanctumUser = Auth::guard('sanctum')->user();
        $customer = Auth::guard('customer')->user();
        $sessionReseller = Auth::guard('reseller')->user();

        if ($sanctumUser instanceof Reseller) {
            $invoice->loadMissing('customer');
            abort_unless(
                $invoice->customer !== null && (int) $invoice->customer->reseller_id === (int) $sanctumUser->getAuthIdentifier(),
                403,
            );
        } elseif ($sessionReseller instanceof Reseller) {
            $invoice->loadMissing('customer');
            abort_unless(
                $invoice->customer !== null && (int) $invoice->customer->reseller_id === (int) $sessionReseller->getAuthIdentifier(),
                403,
            );
        } elseif ($webUser instanceof User) {
            // Staff (Filament / mobile API token)
        } elseif ($customer instanceof Customer) {
            abort_unless((int) $invoice->customer_id === (int) $customer->getAuthIdentifier(), 403);
        } elseif (
            session('bill_pay.verified')
            && session('bill_pay.customer_id')
            && (int) session('bill_pay.customer_id') === (int) $invoice->customer_id
        ) {
            // Public /pay session after OTP
        } else {
            abort(401);
        }

        $invoice->load(['customer', 'items']);

        $html = view('invoices.pdf', array_merge(
            ['invoice' => $invoice],
            ResellerBranding::letterheadVars($invoice->customer),
        ))->render();

        $tmpDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmpDir,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'default_font' => 'dejavusans',
        ]);

        $mpdf->WriteHTML($html);

        $filename = str_replace(['/', '\\'], '-', $invoice->invoice_number).'.pdf';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
