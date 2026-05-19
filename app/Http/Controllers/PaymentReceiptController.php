<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PaymentReceiptController extends Controller
{
    public function show(Payment $payment): Response
    {
        $webUser = Auth::guard('web')->user();
        $customer = Auth::guard('customer')->user();

        if ($webUser) {
            // Staff session
        } elseif ($customer instanceof Customer) {
            abort_unless((int) $payment->customer_id === (int) $customer->getAuthIdentifier(), 403);
        } else {
            abort(401);
        }

        abort_unless($payment->status === 'completed', 404);

        $payment->load(['customer', 'invoice', 'parentPayment']);

        $html = view('payments.receipt', ['payment' => $payment])->render();

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

        $filename = str_replace(['/', '\\'], '-', $payment->receipt_number ?? 'receipt-'.$payment->id).'.pdf';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
