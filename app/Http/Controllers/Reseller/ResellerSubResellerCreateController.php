<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Resellers\ResellerHierarchyService;
use App\Support\ResellerType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResellerSubResellerCreateController extends Controller
{
    public function create(): View
    {
        /** @var Reseller $parent */
        $parent = auth('reseller')->user();

        return view('reseller.sub-resellers.create', [
            'parent' => $parent,
            'types' => ResellerType::labels(),
        ]);
    }

    public function store(Request $request, ResellerHierarchyService $hierarchy): RedirectResponse
    {
        /** @var Reseller $parent */
        $parent = auth('reseller')->user();

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

        return redirect()
            ->route('reseller.sub-resellers.show', $child)
            ->with('status', 'Sub-reseller created successfully.');
    }
}
