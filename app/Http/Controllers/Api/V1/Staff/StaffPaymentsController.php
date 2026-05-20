<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\Collector\CollectorVisitService;
use App\Services\Collector\CollectorWalletService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
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

    public function store(Request $request, CollectorVisitService $visits): JsonResponse
    {
        $user = $this->staff($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(array_keys(PaymentGateway::options()))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = Customer::withoutGlobalScopes()->whereKey($data['customer_id'])->firstOrFail();

        if ($user->tenant_id !== null && (int) $customer->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $data['invoice_id'] ?? null,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $user->id,
        ]);

        $visit = $visits->recordCollection($user, $customer, $payment, [
            'notes' => $data['notes'] ?? null,
            'device_meta' => ['source' => 'staff-mobile-api'],
        ]);

        $wallet = null;
        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager', 'super-admin', 'isp-admin'])) {
            $wallet = app(CollectorWalletService::class)->wallet($user->id);
        }

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'method' => $payment->methodLabel(),
            ],
            'visit_id' => $visit->id,
            'wallet' => $wallet,
        ], 201);
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(self::ACCESS_ROLES), 403);

        return $user;
    }
}
