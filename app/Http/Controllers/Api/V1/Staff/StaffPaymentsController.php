<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\PaymentVoidService;
use App\Services\Billing\StaffCollectionPaymentService;
use App\Services\Collector\CollectorWalletService;
use App\Support\PaymentGateway;
use App\Support\StaffPaymentApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffPaymentsController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
    ];

    public function methods(): JsonResponse
    {
        return response()->json([
            'data' => collect(PaymentGateway::options())
                ->only([PaymentGateway::CASH, PaymentGateway::BKASH, PaymentGateway::NAGAD, PaymentGateway::BANK, PaymentGateway::ROCKET])
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
        ]);
    }

    public function store(Request $request, StaffCollectionPaymentService $collections): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'string', Rule::in(array_keys(PaymentGateway::options()))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_preset' => ['nullable', 'string', 'max:64'],
            'discount_custom' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = Customer::withoutGlobalScopes()->whereKey($data['customer_id'])->firstOrFail();

        if ($user->tenant_id !== null && (int) $customer->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        $result = $collections->record($user, $customer, $data, 'staff-mobile-api');
        $payment = $result['payment']->fresh(['invoice']);

        $wallet = null;
        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager', 'super-admin', 'isp-admin'])) {
            $wallet = app(CollectorWalletService::class)->wallet($user->id);
        }

        return response()->json([
            'message' => $result['message'],
            'payment' => app(StaffPaymentApiPresenter::class)->paymentPayload($payment),
            'discount_bdt' => $result['discount_bdt'],
            'visit_id' => $result['visit_id'],
            'wallet' => $wallet,
        ], 201);
    }

    public function destroy(Request $request, int $paymentId, PaymentVoidService $voids): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $record = Payment::withoutGlobalScopes()->whereKey($paymentId)->firstOrFail();

        if ($user->tenant_id !== null && (int) $record->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        abort_unless(
            $user->hasAnyRole(['super-admin', 'isp-admin', 'admin', 'cashier', 'branch-manager', 'isp-manager']),
            403,
            'Only admin or manager can void payments.',
        );

        $voided = $voids->void($record, $data['reason'] ?? null, $user->id);

        return response()->json([
            'message' => 'Payment voided. Balances adjusted.',
            'payment' => [
                'id' => $voided->id,
                'status' => $voided->status,
                'receipt_number' => $voided->receipt_number,
            ],
        ]);
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(self::ACCESS_ROLES), 403);

        return $user;
    }
}
