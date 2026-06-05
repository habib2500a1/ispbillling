<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\StaffMobileApiAccess;
use App\Support\StaffPaymentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffDocumentController extends Controller
{
    use StaffMobileApiAccess;

    public function invoicePdf(Request $request, int $invoice): Response
    {
        $user = $this->staffMobileUser($request);
        $model = Invoice::withoutGlobalScopes()->whereKey($invoice)->firstOrFail();
        $this->assertStaffTenant($user, (int) $model->tenant_id);

        return app(InvoicePdfController::class)->show($model);
    }

    public function paymentReceiptPdf(Request $request, int $payment): Response
    {
        $user = $this->staffMobileUser($request);
        $model = Payment::withoutGlobalScopes()->whereKey($payment)->firstOrFail();
        $this->assertStaffTenant($user, (int) $model->tenant_id);

        return app(PaymentReceiptController::class)->show($model);
    }

    public function paymentReceiptDetail(Request $request, int $payment, StaffPaymentApiPresenter $presenter): JsonResponse
    {
        $user = $this->staffMobileUser($request);
        $model = Payment::withoutGlobalScopes()->whereKey($payment)->firstOrFail();
        $this->assertStaffTenant($user, (int) $model->tenant_id);
        abort_unless($model->status === 'completed', 404);

        return response()->json([
            'data' => $presenter->receiptDetailPayload($model),
        ]);
    }
}
