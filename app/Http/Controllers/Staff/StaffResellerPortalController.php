<?php

namespace App\Http\Controllers\Staff;

use App\Filament\Resources\ResellerResource;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Resellers\ResellerPortalAccessService;
use App\Services\Resellers\ResellerPortalDeviceTracker;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffResellerPortalController extends Controller
{
    public function login(
        int $reseller,
        Request $request,
        ResellerPortalAccessService $portal,
        ResellerPortalDeviceTracker $devices,
    ): RedirectResponse {
        $record = Reseller::query()->withoutGlobalScopes()->findOrFail($reseller);
        $this->authorizeStaff($record);

        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        $portal->ensurePortalPassword($record);

        Auth::guard('reseller')->login($record->fresh(), false);
        $portal->recordPortalLogin($record);
        $portal->bypassTwoFactorForSession($request);
        $devices->recordLogin($record, $request);
        $request->session()->regenerate();

        return redirect()->route('reseller.dashboard');
    }

    private function authorizeStaff(Reseller $reseller): void
    {
        $user = auth('web')->user() ?? auth()->user();
        if ($user === null || ! ResellerResource::canViewAny()) {
            abort(403);
        }

        if ($user->tenant_id !== null && (int) $reseller->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }

        if (! StaffCapability::for($user)->isTenantAdmin() && ! $user->can('resellers.view')) {
            abort(403);
        }
    }
}
