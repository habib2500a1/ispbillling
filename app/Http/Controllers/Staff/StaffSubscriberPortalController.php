<?php

namespace App\Http\Controllers\Staff;

use App\Filament\Resources\CustomerResource;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Portal\CustomerPortalAccessService;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffSubscriberPortalController extends Controller
{
    public function login(int $customer, Request $request, CustomerPortalAccessService $portal): RedirectResponse
    {
        $record = Customer::query()->withoutGlobalScopes()->findOrFail($customer);
        $this->authorizeStaff($record);

        $portal->ensurePortalPassword($record);

        Auth::guard('customer')->login($record->fresh(), false);
        $record->recordPortalLogin();
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    private function authorizeStaff(Customer $customer): void
    {
        $user = auth('web')->user() ?? auth()->user();
        if ($user === null || ! CustomerResource::canViewAny()) {
            abort(403);
        }

        if ($user->tenant_id !== null && (int) $customer->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }

        if (! StaffCapability::for($user)->isTenantAdmin() && ! $user->can('customers.view')) {
            abort(403);
        }
    }
}
