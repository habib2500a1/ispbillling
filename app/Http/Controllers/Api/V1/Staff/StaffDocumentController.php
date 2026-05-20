<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentReceiptController;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffDocumentController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
    ];

    public function invoicePdf(Request $request, int $invoice): Response
    {
        $user = $this->staff($request);
        $model = Invoice::withoutGlobalScopes()->whereKey($invoice)->firstOrFail();
        $this->assertTenant($user, (int) $model->tenant_id);

        return app(InvoicePdfController::class)->show($model);
    }

    public function paymentReceiptPdf(Request $request, int $payment): Response
    {
        $user = $this->staff($request);
        $model = Payment::withoutGlobalScopes()->whereKey($payment)->firstOrFail();
        $this->assertTenant($user, (int) $model->tenant_id);

        return app(PaymentReceiptController::class)->show($model);
    }

    private function staff(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasAnyRole(self::ACCESS_ROLES), 403);

        return $user;
    }

    private function assertTenant(User $user, int $tenantId): void
    {
        if ($user->tenant_id !== null && (int) $user->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
