<?php

namespace App\Services\Rbac;

use App\Services\Staff\ActivityLogger;
use App\Support\Rbac\IspPermissionCatalog;
use App\Support\Rbac\IspRoleTemplates;
use Illuminate\Support\Collection;
use App\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function syncCatalog(): int
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $created = 0;
        foreach (IspPermissionCatalog::grouped() as $categoryKey => $permissions) {
            foreach ($permissions as $name => $label) {
                $permission = Permission::findOrCreate($name, 'web');
                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
                if ($permission->display_name !== $label || $permission->category !== $categoryKey) {
                    $permission->update([
                        'display_name' => $label,
                        'category' => $categoryKey,
                    ]);
                }
            }
        }

        return $created;
    }

    public function applyTemplate(Role $role, string $templateSlug): void
    {
        $keys = IspRoleTemplates::permissionKeysFor($templateSlug);
        $this->syncRolePermissions($role, $keys, "Applied template: {$templateSlug}");
    }

    public function cloneRole(Role $role, string $newName): Role
    {
        $clone = Role::findOrCreate($newName, 'web');
        $clone->syncPermissions($role->permissions->pluck('name')->all());

        $this->activityLogger->log(
            'rbac.role.cloned',
            "Role cloned from {$role->name} → {$newName}",
            $clone,
            ['source_role' => $role->name],
        );

        return $clone;
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function syncRolePermissions(Role $role, array $permissionNames, ?string $note = null): void
    {
        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $valid = array_values(array_intersect($permissionNames, IspPermissionCatalog::all()));
        $role->syncPermissions($valid);
        $after = $role->fresh()->permissions()->pluck('name')->sort()->values()->all();

        $this->logPermissionChange($role, $before, $after, $note);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public function logPermissionChange(Role $role, array $before, array $after, ?string $note = null): void
    {
        if ($before === $after) {
            return;
        }

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        $this->activityLogger->log(
            'rbac.role.permissions',
            $note ?? "Permissions updated for role {$role->name}",
            $role,
            [
                'role' => $role->name,
                'added' => $added,
                'removed' => $removed,
                'count' => count($after),
            ],
            'rbac',
        );
    }

    /**
     * @return Collection<int, \App\Models\ActivityLog>
     */
    public function auditTimelineForRole(Role $role, int $limit = 15): Collection
    {
        return \App\Models\ActivityLog::query()
            ->where('log_name', 'rbac')
            ->where(function ($q) use ($role): void {
                $q->where('subject_type', Role::class)
                    ->where('subject_id', $role->id)
                    ->orWhere('properties->role', $role->name);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function seedAllTemplates(): void
    {
        $this->syncCatalog();

        foreach (IspRoleTemplates::all() as $slug => $meta) {
            if ($slug === 'customer') {
                continue;
            }

            $role = Role::findOrCreate($slug, 'web');
            $keys = IspRoleTemplates::permissionKeysFor($slug);
            if ($keys !== []) {
                $role->syncPermissions($keys);
            }

            foreach ($meta['legacy'] ?? [] as $legacySlug) {
                $legacy = Role::findOrCreate($legacySlug, 'web');
                $legacy->syncPermissions($keys);
            }
        }
    }
}
