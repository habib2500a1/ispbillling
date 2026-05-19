<?php

namespace App\Support\Rbac;

use App\Models\Permission;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

final class PermissionMatrixData
{
    /**
     * @return Collection<int, Role>
     */
    public static function roles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'customer')
            ->orderByRaw("CASE name
                WHEN 'super-admin' THEN 0
                WHEN 'isp-admin' THEN 1
                WHEN 'isp-owner' THEN 2
                WHEN 'admin' THEN 3
                ELSE 50 END")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, array{label: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function groupedPermissions(?string $search = null): array
    {
        $search = mb_strtolower(trim((string) $search));
        $db = Permission::query()->get()->keyBy('name');

        $groups = [];

        foreach (IspPermissionCatalog::grouped() as $categoryKey => $permissions) {
            $label = IspPermissionCatalog::categoryLabels()[$categoryKey] ?? $categoryKey;
            $rows = [];

            foreach ($permissions as $name => $defaultLabel) {
                $record = $db->get($name);
                $display = $record?->display_name ?? $defaultLabel;

                if ($search !== '' && ! self::matchesSearch($search, $name, $display, $label)) {
                    continue;
                }

                $rows[] = [
                    'name' => $name,
                    'label' => $display,
                ];
            }

            if ($rows !== []) {
                $groups[$categoryKey] = [
                    'label' => $label,
                    'permissions' => $rows,
                ];
            }
        }

        $known = [];
        foreach (IspPermissionCatalog::grouped() as $permissions) {
            foreach (array_keys($permissions) as $key) {
                $known[$key] = true;
            }
        }

        $custom = [];
        foreach ($db as $permission) {
            if (isset($known[$permission->name])) {
                continue;
            }

            $display = $permission->resolvedLabel();
            $catLabel = $permission->resolvedCategory() ?? 'Custom';

            if ($search !== '' && ! self::matchesSearch($search, $permission->name, $display, $catLabel)) {
                continue;
            }

            $categoryKey = $permission->category ?? 'custom';
            $custom[$categoryKey]['label'] ??= $catLabel;
            $custom[$categoryKey]['permissions'][] = [
                'name' => $permission->name,
                'label' => $display,
            ];
        }

        foreach ($custom as $categoryKey => $group) {
            if (($group['permissions'] ?? []) === []) {
                continue;
            }
            $groups[$categoryKey] = $group;
        }

        return $groups;
    }

    public static function roleLabel(Role $role): string
    {
        return IspRoleTemplates::get($role->name)['label'] ?? str($role->name)->headline()->toString();
    }

    public static function roleDescription(Role $role): string
    {
        return IspRoleTemplates::get($role->name)['description'] ?? $role->name;
    }

    /**
     * @param  array<int, array<string, true>>  $rolePermissions
     */
    public static function roleHas(array $rolePermissions, int $roleId, string $permission): bool
    {
        return isset($rolePermissions[$roleId][$permission]);
    }

    private static function matchesSearch(string $search, string $name, string $label, string $category): bool
    {
        $haystack = mb_strtolower("{$category} {$label} {$name}");

        return str_contains($haystack, $search);
    }
}
