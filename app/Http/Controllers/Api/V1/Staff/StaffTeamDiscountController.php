<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\CollectionDiscountSettings;
use App\Support\UserCollectionDiscount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffTeamDiscountController extends Controller
{
    private const ADMIN_ROLES = ['super-admin', 'isp-admin', 'admin', 'isp-manager'];

    public function index(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        $query = User::withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('name');

        if ($admin->tenant_id !== null) {
            $query->where('tenant_id', $admin->tenant_id);
        }

        $staff = $query->get()->filter(fn (User $u): bool => $u->id !== $admin->id
            && $u->hasAnyRole(['cashier', 'collector', 'branch-manager', 'isp-manager', 'isp-admin']));

        $global = CollectionDiscountSettings::get();

        return response()->json([
            'global' => [
                'enabled' => $global['enabled'],
                'max_discount_bdt' => $global['max_discount_bdt'],
                'max_discount_percent_of_due' => $global['max_discount_percent_of_due'],
            ],
            'data' => $staff->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->getRoleNames()->values()->all(),
                'can_billing_discount' => $u->can('billing.discount'),
                'collection_discount' => UserCollectionDiscount::prefs($u),
            ])->values(),
        ]);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $admin = $this->admin($request);

        $model = User::withoutGlobalScopes()->whereKey($user)->firstOrFail();
        if ($admin->tenant_id !== null && (int) $model->tenant_id !== (int) $admin->tenant_id) {
            abort(404);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'max_discount_bdt' => ['nullable', 'numeric', 'min:0'],
            'max_discount_percent_of_due' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $model->forceFill([
            'dashboard_preferences' => UserCollectionDiscount::mergeIntoDashboardPreferences($model, $data),
        ])->save();

        return response()->json([
            'message' => 'Staff discount limits saved.',
            'user' => [
                'id' => $model->id,
                'name' => $model->name,
                'collection_discount' => UserCollectionDiscount::prefs($model->fresh()),
            ],
        ]);
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(self::ADMIN_ROLES), 403);

        return $user;
    }
}
