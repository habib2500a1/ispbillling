<?php

namespace App\Services\Tenant;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantSubscriptionService;
use App\Support\CompanyBranding;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\StaffCapability;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class TenantOrganizationIntelligenceService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'tenant_org:snapshot:'.$tenantId,
            now()->addSeconds(60),
            fn (): array => [
                'tenant' => $this->organizationProfile($tenantId),
                'kpis' => $this->tenantKpis($tenantId),
                'staff_breakdown' => $this->staffBreakdown($tenantId),
                'branches' => $this->branchSnapshots($tenantId),
                'resellers' => $this->resellerSummary($tenantId),
                'security' => $this->securitySummary($tenantId),
                'activity' => $this->recentActivity($tenantId),
                'roles' => $this->roleSummary(),
                'white_label' => $this->whiteLabel($tenantId),
                'subscription' => app(TenantSubscriptionService::class)->forTenant($tenantId),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $q = trim(mb_strtolower($query));
        if (mb_strlen($q) < 2) {
            return [];
        }

        $results = [];

        User::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($q): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$q.'%']);
            })
            ->limit(8)
            ->get(['id', 'name', 'email'])
            ->each(function (User $user) use (&$results): void {
                $results[] = [
                    'type' => 'staff',
                    'label' => $user->name,
                    'meta' => $user->email,
                    'url' => \App\Filament\Resources\UserResource::getUrl('edit', ['record' => $user->id]),
                ];
            });

        Role::query()
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
            ->limit(6)
            ->get(['id', 'name'])
            ->each(function (Role $role) use (&$results): void {
                $results[] = [
                    'type' => 'role',
                    'label' => $role->name,
                    'meta' => 'RBAC role',
                    'url' => \App\Filament\Resources\RoleResource::getUrl('edit', ['record' => $role->id]),
                ];
            });

        Branch::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($q): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%'.$q.'%']);
            })
            ->limit(6)
            ->get(['id', 'name', 'code'])
            ->each(function (Branch $branch) use (&$results): void {
                $results[] = [
                    'type' => 'branch',
                    'label' => $branch->name,
                    'meta' => $branch->code ?? 'Branch',
                    'url' => \App\Filament\Resources\BranchResource::getUrl('edit', ['record' => $branch->id]),
                ];
            });

        Reseller::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($q): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                    ->orWhereRaw('LOWER(code) LIKE ?', ['%'.$q.'%']);
            })
            ->limit(6)
            ->get(['id', 'name', 'code'])
            ->each(function (Reseller $reseller) use (&$results): void {
                $results[] = [
                    'type' => 'reseller',
                    'label' => $reseller->name,
                    'meta' => $reseller->code ?? 'Reseller',
                    'url' => \App\Filament\Resources\ResellerResource::getUrl('edit', ['record' => $reseller->id]),
                ];
            });

        foreach (IspPermissionCatalog::grouped() as $category => $permissions) {
            foreach ($permissions as $key => $label) {
                if (str_contains(mb_strtolower($key.' '.$label), $q)) {
                    $results[] = [
                        'type' => 'permission',
                        'label' => $label,
                        'meta' => $category.' · '.$key,
                        'url' => \App\Filament\Pages\PermissionMatrix::getUrl(),
                    ];
                    if (count($results) >= 24) {
                        break 2;
                    }
                }
            }
        }

        if (StaffCapability::for(auth()->user())->isTenantAdmin()) {
            Tenant::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%'])
                ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.$q.'%'])
                ->limit(4)
                ->get(['id', 'name', 'slug'])
                ->each(function (Tenant $tenant) use (&$results): void {
                    $results[] = [
                        'type' => 'tenant',
                        'label' => $tenant->name,
                        'meta' => $tenant->slug,
                        'url' => \App\Filament\Resources\TenantResource::getUrl('edit', ['record' => $tenant->id]),
                    ];
                });
        }

        return array_slice($results, 0, 20);
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationProfile(int $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);
        $branding = is_array($tenant?->branding) ? $tenant->branding : [];

        return [
            'id' => $tenantId,
            'name' => $tenant?->name ?? CompanyBranding::name(),
            'slug' => $tenant?->slug ?? 'default',
            'organization_type' => $tenant?->organization_type ?? 'single_isp',
            'organization_type_label' => $this->organizationTypeLabel($tenant?->organization_type ?? 'single_isp'),
            'domain' => $tenant?->domain ?? parse_url((string) config('app.url'), PHP_URL_HOST),
            'address' => $tenant?->address ?? CompanyBranding::address(),
            'contact_phone' => $tenant?->contact_phone ?? CompanyBranding::phone(),
            'contact_email' => $tenant?->contact_email ?? CompanyBranding::email(),
            'logo_url' => CompanyBranding::logoUrl(),
            'is_active' => (bool) ($tenant?->is_active ?? true),
            'app_name' => (string) ($branding['app_name'] ?? CompanyBranding::name()),
            'theme' => (string) ($branding['theme'] ?? 'default'),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function tenantKpis(int $tenantId): array
    {
        $monthStart = now()->startOfMonth();

        return [
            'total_customers' => Customer::query()->where('tenant_id', $tenantId)->count(),
            'total_staff' => User::query()->where('tenant_id', $tenantId)->count(),
            'active_staff' => User::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'total_revenue' => (float) Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where('paid_at', '>=', $monthStart)
                ->sum('amount'),
            'total_routers' => MikrotikServer::query()->where('tenant_id', $tenantId)->count(),
            'total_olts' => Device::query()->where('tenant_id', $tenantId)->where('type', 'olt')->count(),
            'total_onus' => Device::query()->where('tenant_id', $tenantId)->where('type', 'onu')->count(),
            'active_tickets' => SupportTicket::query()
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['closed', 'resolved', 'cancelled'])
                ->count(),
            'branches' => Branch::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'resellers' => Reseller::query()->where('tenant_id', $tenantId)->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffBreakdown(int $tenantId): array
    {
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with('roles:id,name')
            ->orderByDesc('last_login_at')
            ->limit(12)
            ->get(['id', 'name', 'email', 'branch_id', 'is_active', 'two_factor_confirmed_at', 'last_login_at']);

        return $users->map(function (User $user): array {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'branch_id' => $user->branch_id,
                'is_active' => (bool) $user->is_active,
                'has_2fa' => $user->two_factor_confirmed_at !== null,
                'last_login_at' => $user->last_login_at?->diffForHumans(),
                'url' => \App\Filament\Resources\UserResource::getUrl('edit', ['record' => $user->id]),
                'tickets_assigned' => $user->assignedSupportTickets()->whereNotIn('status', ['closed', 'resolved'])->count(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branchSnapshots(int $tenantId): array
    {
        return Branch::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'manager_name', 'phone', 'is_active'])
            ->map(function (Branch $branch) use ($tenantId): array {
                $staffIds = User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branch->id)
                    ->pluck('id');
                $staffCount = $staffIds->count();
                $revenue = $staffCount > 0
                    ? (float) Payment::query()
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'completed')
                        ->whereIn('recorded_by', $staffIds)
                        ->where('paid_at', '>=', now()->startOfMonth())
                        ->sum('amount')
                    : 0.0;

                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'manager' => $branch->manager_name,
                    'phone' => $branch->phone,
                    'is_active' => (bool) $branch->is_active,
                    'staff' => $staffCount,
                    'revenue_mtd' => round($revenue, 2),
                    'tickets' => 0,
                    'url' => \App\Filament\Resources\BranchResource::getUrl('edit', ['record' => $branch->id]),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resellerSummary(int $tenantId): array
    {
        $active = Reseller::query()->where('tenant_id', $tenantId)->where('is_active', true)->count();
        $total = Reseller::query()->where('tenant_id', $tenantId)->count();
        $customers = Customer::query()->where('tenant_id', $tenantId)->whereNotNull('reseller_id')->count();

        return [
            'total' => $total,
            'active' => $active,
            'customers' => $customers,
            'white_label_enabled' => Reseller::query()->where('tenant_id', $tenantId)->where('white_label_enabled', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function securitySummary(int $tenantId): array
    {
        $base = ActivityLog::query()->where('tenant_id', $tenantId);

        return [
            'logins_today' => (clone $base)->where('event', 'login')->whereDate('created_at', today())->count(),
            'failed_today' => (clone $base)->where('event', 'login.failed')->whereDate('created_at', today())->count(),
            'with_2fa' => User::query()->where('tenant_id', $tenantId)->whereNotNull('two_factor_confirmed_at')->count(),
            'inactive_staff' => User::query()->where('tenant_id', $tenantId)->where('is_active', false)->count(),
            'recent_logins' => (clone $base)->where('event', 'login')->latest()->limit(8)->get(['description', 'ip_address', 'created_at'])->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivity(int $tenantId): array
    {
        return ActivityLog::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit(15)
            ->get(['event', 'log_name', 'description', 'ip_address', 'created_at', 'user_id'])
            ->map(fn (ActivityLog $log): array => [
                'event' => $log->event,
                'log_name' => $log->log_name,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'at' => $log->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roleSummary(): array
    {
        return Role::query()
            ->withCount('permissions', 'users')
            ->orderBy('name')
            ->limit(16)
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => (int) $role->permissions_count,
                'users' => (int) $role->users_count,
                'url' => \App\Filament\Resources\RoleResource::getUrl('edit', ['record' => $role->id]),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function whiteLabel(int $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);
        $branding = is_array($tenant?->branding) ? $tenant->branding : [];

        return [
            'logo_url' => CompanyBranding::logoUrl(),
            'app_name' => (string) ($branding['app_name'] ?? CompanyBranding::name()),
            'primary_color' => (string) ($branding['primary_color'] ?? '#4f46e5'),
            'accent_color' => (string) ($branding['accent_color'] ?? '#0ea5e9'),
            'theme' => (string) ($branding['theme'] ?? 'default'),
            'company_setup_url' => \App\Filament\Pages\ManageCompanySetup::getUrl(),
            'portal_settings_url' => \App\Filament\Pages\ManagePortalSettings::getUrl(),
            'mobile_app_url' => \App\Filament\Pages\ManageMobileApp::getUrl(),
        ];
    }

    private function organizationTypeLabel(string $type): string
    {
        return match ($type) {
            'multi_isp' => 'Multi ISP',
            'multi_branch' => 'Multi Branch',
            'franchise' => 'Franchise ISP',
            'reseller_isp' => 'Reseller ISP',
            default => 'Single ISP',
        };
    }
}
