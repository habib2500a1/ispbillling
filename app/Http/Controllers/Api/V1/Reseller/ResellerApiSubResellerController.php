<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Resellers\ResellerHierarchyService;
use App\Support\ResellerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ResellerApiSubResellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $partners = $reseller->children()
            ->withCount('customers')
            ->orderBy('name')
            ->get()
            ->map(fn (Reseller $partner) => $this->partnerPayload($partner));

        return response()->json(['partners' => $partners]);
    }

    public function show(Request $request, Reseller $child): JsonResponse
    {
        $reseller = $request->user();
        abort_unless((int) $child->parent_id === (int) $reseller->id, 404);
        $child->loadCount('customers');

        return response()->json([
            'partner' => $this->partnerPayload($child, true),
            'stats' => $child->dashboardStats(),
        ]);
    }

    public function store(Request $request, ResellerHierarchyService $hierarchy): JsonResponse
    {
        $parent = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'franchise_type' => ['required', 'string', Rule::in(array_keys(ResellerType::labels()))],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'portal_login' => ['required', 'string', 'max:64'],
            'portal_password' => ['required', 'string', 'min:8', 'max:128'],
            'commission_type' => ['required', 'in:percent,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'max_clients' => ['nullable', 'integer', 'min:0'],
        ]);

        $child = Reseller::query()->create([
            'tenant_id' => $parent->tenant_id,
            'parent_id' => $parent->id,
            'franchise_type' => $validated['franchise_type'],
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'portal_login' => $validated['portal_login'],
            'portal_password' => Hash::make($validated['portal_password']),
            'commission_type' => $validated['commission_type'],
            'commission_value' => $validated['commission_value'],
            'max_clients' => $validated['max_clients'] ?? null,
            'wallet_balance' => 0,
            'bonus_wallet_balance' => 0,
            'is_active' => true,
        ]);

        $hierarchy->syncPath($child);
        $child->loadCount('customers');

        return response()->json([
            'partner' => $this->partnerPayload($child, true),
            'message' => 'Sub-partner created.',
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerPayload(Reseller $partner, bool $detailed = false): array
    {
        $payload = [
            'id' => $partner->id,
            'code' => $partner->code,
            'name' => $partner->name,
            'franchise_type' => $partner->franchise_type,
            'franchise_type_label' => $partner->franchiseTypeLabel(),
            'customers_count' => (int) ($partner->customers_count ?? 0),
            'wallet_balance' => (float) $partner->wallet_balance,
            'is_active' => (bool) $partner->is_active,
        ];

        if ($detailed) {
            $payload['phone'] = $partner->phone;
            $payload['email'] = $partner->email;
            $payload['commission_label'] = $partner->commissionLabel();
        }

        return $payload;
    }
}
