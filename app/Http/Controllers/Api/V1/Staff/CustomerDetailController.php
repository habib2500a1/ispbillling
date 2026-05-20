<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Billing\BillCollectionSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDetailController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
    ];

    public function show(Request $request, int $customer, BillCollectionSearchService $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(self::ACCESS_ROLES)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $data = $search->find($customer);
        if ($data === null) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        return response()->json(['customer' => $data]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(self::ACCESS_ROLES)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $q = trim((string) $request->query('q', ''));

        $query = Customer::query()
            ->select(['id', 'customer_code', 'name', 'phone', 'status', 'package_id'])
            ->with('package:id,name')
            ->orderBy('name');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like, $q): void {
                $w->where('customer_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', (int) $request->query('package_id'));
        }

        if ($request->boolean('due_only')) {
            $query->whereHas('invoices', fn ($iq) => $iq
                ->whereIn('status', ['open', 'partial'])
                ->whereRaw('(total - amount_paid) > 0.009'));
        }

        if ($request->filled('expiring_days')) {
            $days = max(1, min(90, (int) $request->query('expiring_days')));
            $query->whereNotNull('service_expires_at')
                ->where('service_expires_at', '<=', now()->addDays($days)->endOfDay())
                ->where('service_expires_at', '>=', now()->startOfDay());
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $customers = collect($paginated->items())->map(fn (Customer $c) => [
            'id' => $c->id,
            'customer_code' => $c->customer_code,
            'name' => $c->name,
            'phone' => $c->phone,
            'status' => $c->status,
            'package' => $c->package?->name,
            'is_online' => $c->isPppOnline(),
        ]);

        return response()->json([
            'data' => $customers,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
