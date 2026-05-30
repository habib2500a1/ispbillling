<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerStaff;
use App\Services\Resellers\ResellerStaffService;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerStaffController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $staff = ResellerStaff::query()
            ->where('reseller_id', $reseller->id)
            ->orderBy('name')
            ->paginate(20);

        return view('reseller.staff.index', [
            'reseller' => $reseller,
            'staff' => $staff,
            'portal' => app(ResellerPortalSession::class),
        ]);
    }

    public function create(ResellerStaffService $staffService): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.staff.create', [
            'reseller' => $reseller,
            'permissionOptions' => $staffService->permissionOptions($reseller),
            'defaultPermissions' => [
                \App\Support\ResellerPortalPermission::CUSTOMER_VIEW,
                \App\Support\ResellerPortalPermission::BILLING_VIEW,
                \App\Support\ResellerPortalPermission::PAYMENT_COLLECT,
            ],
        ]);
    }

    public function store(Request $request, ResellerStaffService $staffService): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $staffService->create($reseller, $request->all());

        return redirect()
            ->route('reseller.staff.index')
            ->with('status', 'Staff account created.');
    }

    public function edit(ResellerStaff $staffMember, ResellerStaffService $staffService): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $this->assertStaffBelongsToReseller($staffMember, $reseller);

        return view('reseller.staff.edit', [
            'reseller' => $reseller,
            'staffMember' => $staffMember,
            'permissionOptions' => $staffService->permissionOptions($reseller),
        ]);
    }

    public function update(Request $request, ResellerStaff $staffMember, ResellerStaffService $staffService): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $this->assertStaffBelongsToReseller($staffMember, $reseller);

        $staffService->update($staffMember, $reseller, $request->all());

        return redirect()
            ->route('reseller.staff.index')
            ->with('status', 'Staff account updated.');
    }

    public function destroy(ResellerStaff $staffMember): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $this->assertStaffBelongsToReseller($staffMember, $reseller);

        $staffMember->forceFill(['is_active' => false])->save();

        return redirect()
            ->route('reseller.staff.index')
            ->with('status', 'Staff account deactivated.');
    }

    private function assertStaffBelongsToReseller(ResellerStaff $staffMember, \App\Models\Reseller $reseller): void
    {
        if ((int) $staffMember->reseller_id !== (int) $reseller->id) {
            abort(404);
        }
    }
}
