<?php

namespace App\Http\Controllers\Api\V1\Collector;

use App\Http\Controllers\Controller;
use App\Models\CollectorVisit;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Billing\BillCollectionSearchService;
use App\Services\Collector\CollectorVisitService;
use App\Services\Collector\CollectorWalletService;
use App\Services\Collector\CollectorSettlementService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectorController extends Controller
{
    public function searchCustomers(Request $request, BillCollectionSearchService $search): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $results = $search->search($q)->take(20)->values();

        return response()->json(['data' => $results]);
    }

    public function todayVisits(Request $request): JsonResponse
    {
        $user = $request->user();
        $visits = CollectorVisit::query()
            ->where('collector_id', $user->id)
            ->whereDate('visited_at', today())
            ->with(['customer:id,name,customer_code,phone'])
            ->orderByDesc('visited_at')
            ->limit(50)
            ->get()
            ->map(fn (CollectorVisit $v) => [
                'id' => $v->id,
                'customer' => $v->customer?->only(['id', 'name', 'customer_code', 'phone']),
                'amount_collected' => $v->amount_collected,
                'payment_method' => $v->payment_method,
                'latitude' => $v->latitude,
                'longitude' => $v->longitude,
                'visited_at' => $v->visited_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $visits]);
    }

    public function storeCollection(Request $request, CollectorVisitService $visits): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'location_text' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $data['invoice_id'] ?? null,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'] ?: PaymentGateway::CASH,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $request->user()->id,
        ]);

        $visit = $visits->recordCollection($request->user(), $customer, $payment, [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'notes' => $data['notes'] ?? null,
            'device_meta' => ['source' => 'collector-api'],
        ]);

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => $payment->amount,
            ],
            'visit_id' => $visit->id,
            'wallet' => app(CollectorWalletService::class)->wallet((int) $request->user()->id),
        ], 201);
    }

    public function wallet(Request $request, CollectorWalletService $wallet): JsonResponse
    {
        return response()->json([
            'data' => $wallet->wallet((int) $request->user()->id),
            'alerts' => $wallet->fraudAlerts((int) $request->user()->id),
        ]);
    }

    public function storeExpense(Request $request, CollectorWalletService $wallet): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['required', 'integer', 'exists:collector_expense_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'expense_date' => ['nullable', 'date'],
        ]);

        $expense = $wallet->submitExpense(
            collectorId: (int) $request->user()->id,
            amount: (float) $data['amount'],
            categoryId: (int) $data['category_id'],
            description: $data['description'] ?? null,
            expenseDate: $data['expense_date'] ?? null,
        );

        return response()->json(['data' => $expense->load('category')], 201);
    }

    public function storeSettlement(Request $request, CollectorSettlementService $settlements): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $settlement = $settlements->submitSettlement(
            collectorId: (int) $request->user()->id,
            amount: (float) $data['amount'],
            paymentMethod: $data['payment_method'] ?? 'cash',
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return response()->json(['data' => $settlement], 201);
    }

    public function storeDailyClosing(Request $request, CollectorWalletService $wallet): JsonResponse
    {
        $data = $request->validate([
            'closing_date' => ['nullable', 'date'],
            'declared_cash_in_hand' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $closing = $wallet->submitDailyClosing(
            collectorId: (int) $request->user()->id,
            closingDate: $data['closing_date'] ?? now()->toDateString(),
            declaredCashInHand: (float) $data['declared_cash_in_hand'],
            notes: $data['notes'] ?? null,
        );

        return response()->json(['data' => $closing], 201);
    }
}
