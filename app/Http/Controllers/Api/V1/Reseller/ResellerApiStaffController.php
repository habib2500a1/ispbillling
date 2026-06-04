<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerStaff;
use App\Services\Resellers\ResellerStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiStaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $staff = ResellerStaff::query()
            ->where('reseller_id', $reseller->id)
            ->orderBy('name')
            ->get()
            ->map(fn (ResellerStaff $member) => $this->staffPayload($member));

        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request, ResellerStaffService $staffService): JsonResponse
    {
        $reseller = $request->user();
        $member = $staffService->create($reseller, $request->all());

        return response()->json([
            'staff' => $this->staffPayload($member, includeMeta: true),
            'message' => 'Staff account created.',
        ], 201);
    }

    public function show(Request $request, ResellerStaff $staffMember): JsonResponse
    {
        $this->assertOwned($request, $staffMember);

        return response()->json(['staff' => $this->staffPayload($staffMember)]);
    }

    public function update(Request $request, ResellerStaff $staffMember, ResellerStaffService $staffService): JsonResponse
    {
        $reseller = $request->user();
        $this->assertOwned($request, $staffMember);

        $member = $staffService->update($staffMember, $reseller, $request->all());

        return response()->json([
            'staff' => $this->staffPayload($member),
            'message' => 'Staff account updated.',
        ]);
    }

    public function destroy(Request $request, ResellerStaff $staffMember): JsonResponse
    {
        $this->assertOwned($request, $staffMember);
        $staffMember->forceFill(['is_active' => false])->save();

        return response()->json(['message' => 'Staff account deactivated.']);
    }

    public function permissionOptions(Request $request, ResellerStaffService $staffService): JsonResponse
    {
        return response()->json([
            'options' => $staffService->permissionOptions($request->user()),
        ]);
    }

    private function assertOwned(Request $request, ResellerStaff $staffMember): void
    {
        abort_unless((int) $staffMember->reseller_id === (int) $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(ResellerStaff $member, bool $includeMeta = false): array
    {
        $payload = [
            'id' => $member->id,
            'name' => $member->name,
            'login' => $member->login,
            'email' => $member->email,
            'phone' => $member->phone,
            'is_active' => (bool) $member->is_active,
            'portal_permissions' => $member->portalPermissions(),
            'last_login_at' => $member->last_login_at?->toIso8601String(),
        ];

        if ($includeMeta && is_array($member->meta) && isset($member->meta['portal_password_plain'])) {
            $payload['initial_password'] = $member->meta['portal_password_plain'];
        }

        return $payload;
    }
}
