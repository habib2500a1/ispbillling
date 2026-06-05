<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesBillingDocumentAccess;
use App\Models\Payment;
use App\Support\ResellerBranding;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PaymentReceiptController extends Controller
{
    use AuthorizesBillingDocumentAccess;

    public function show(Payment $payment): Response
    {
        $this->authorizePaymentReceipt($payment);

        abort_unless($payment->status === 'completed', 404);

        $payment->load(['customer', 'invoice', 'parentPayment', 'recorder:id,name']);

        $html = view('payments.receipt', array_merge(
            ['payment' => $payment],
            ResellerBranding::letterheadVars($payment->customer),
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

        $filename = str_replace(['/', '\\'], '-', $payment->receipt_number ?? 'receipt-'.$payment->id).'.pdf';

        return new Response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
