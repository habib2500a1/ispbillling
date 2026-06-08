<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Filament\Pages\TenantOrganizationCenter;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\Tenant\TenantOrganizationIntelligenceService;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class StaffAdminController extends Controller
{
    public function overview(Request $request, TenantOrganizationIntelligenceService $intel): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'organization' => $intel->snapshot($request->user()->tenant_id),
            'web_url' => TenantOrganizationCenter::getUrl(),
        ]);
    }

    public function search(Request $request, TenantOrganizationIntelligenceService $intel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = (string) $request->query('q', '');

        return response()->json([
            'query' => $query,
            'results' => $intel->search($query, $request->user()->tenant_id),
        ]);
    }

    public function staff(Request $request, TenantOrganizationIntelligenceService $intel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $snapshot = $intel->snapshot($request->user()->tenant_id);

        return response()->json([
            'data' => $snapshot['staff_breakdown'] ?? [],
            'total' => $snapshot['kpis']['total_staff'] ?? 0,
        ]);
    }

    public function roles(Request $request, TenantOrganizationIntelligenceService $intel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $snapshot = $intel->snapshot($request->user()->tenant_id);

        return response()->json([
            'data' => $snapshot['roles'] ?? [],
            'total' => $snapshot['kpis']['roles'] ?? 0,
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $groups = [];
        foreach (IspPermissionCatalog::grouped() as $category => $permissions) {
            $groups[] = [
                'category' => $category,
                'permissions' => collect($permissions)->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                ])->values()->all(),
            ];
        }

        return response()->json(['groups' => $groups]);
    }

    public function branches(Request $request, TenantOrganizationIntelligenceService $intel): JsonResponse
    {
        $this->authorizeAdmin($request);

        $snapshot = $intel->snapshot($request->user()->tenant_id);

        return response()->json([
            'data' => $snapshot['branches'] ?? [],
            'total' => $snapshot['kpis']['branches'] ?? 0,
        ]);
    }

    public function showStaff(Request $request, int $user): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $model = User::withoutGlobalScopes()
            ->with(['roles:id,name', 'branch:id,name'])
            ->whereKey($user)
            ->firstOrFail();

        if ($admin->tenant_id !== null && (int) $model->tenant_id !== (int) $admin->tenant_id) {
            abort(404);
        }

        return response()->json([
            'id' => $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'mobile' => null,
            'roles' => $model->roles->pluck('name')->values()->all(),
            'department' => null,
            'branch' => $model->branch?->name,
            'is_active' => (bool) $model->is_active,
            'has_2fa' => $model->two_factor_confirmed_at !== null,
            'last_login_at' => $model->last_login_at?->toIso8601String(),
            'tickets_assigned' => $model->assignedSupportTickets()
                ->whereNotIn('status', ['closed', 'resolved'])
                ->count(),
        ]);
    }

    public function showRole(Request $request, int $role): JsonResponse
    {
        $this->authorizeAdmin($request);

        $model = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->whereKey($role)
            ->firstOrFail();

        return response()->json([
            'id' => $model->id,
            'name' => $model->name,
            'permissions' => $model->permissions->pluck('name')->values()->all(),
            'users' => (int) $model->users_count,
        ]);
    }

    public function showBranch(Request $request, int $branch): JsonResponse
    {
        $admin = $this->authorizeAdmin($request);

        $model = Branch::query()->whereKey($branch)->firstOrFail();
        if ($admin->tenant_id !== null && (int) $model->tenant_id !== (int) $admin->tenant_id) {
            abort(404);
        }

        $intel = app(TenantOrganizationIntelligenceService::class);
        $snapshot = collect($intel->snapshot($admin->tenant_id)['branches'] ?? [])
            ->firstWhere('id', $model->id);

        return response()->json($snapshot ?? [
            'id' => $model->id,
            'name' => $model->name,
            'code' => $model->code,
            'manager' => $model->manager_name,
            'is_active' => (bool) $model->is_active,
        ]);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $cap = StaffCapability::for($user);
        $allowed = $cap->canStaffModule()
            || $cap->canAny(['security.manage', 'security.roles', 'audit.view', 'branches.view', 'branches.manage'])
            || $user->hasRole('super-admin');

        abort_unless($allowed, 403);

        return $user;
    }
}
