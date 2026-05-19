<?php

namespace App\Filament\Pages;

use App\Services\Rbac\RolePermissionService;
use App\Support\Rbac\PermissionMatrixData;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class PermissionMatrix extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.permission-matrix';

    protected static ?string $navigationLabel = 'Permission matrix';

    protected static ?string $title = 'Roles & permission matrix';

    protected static ?string $slug = 'permission-matrix';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public string $search = '';

    /** @var array<string> */
    public array $expandedCategories = [];

    public ?string $focusRole = null;

    /** @var array<int, array<string, true>> */
    public array $rolePermissions = [];

    public function mount(): void
    {
        $this->focusRole = request()->query('role');
        $this->reloadRolePermissions();
        $this->expandedCategories = array_keys(PermissionMatrixData::groupedPermissions());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin')
                || $user->hasRole('isp-admin')
                || $user->can('security.roles'));
    }

    public function togglePermission(int $roleId, string $permissionName): void
    {
        $role = Role::query()->findOrFail($roleId);

        if (! $this->canEditRole($role)) {
            Notification::make()
                ->title('This role is protected')
                ->warning()
                ->send();

            return;
        }

        app(RolePermissionService::class)->togglePermission($role, $permissionName);

        $fresh = $role->fresh(['permissions']);
        $this->rolePermissions[$roleId] = $fresh
            ->permissions
            ->pluck('name')
            ->flip()
            ->all();
    }

    public function toggleCategory(string $categoryKey): void
    {
        if (in_array($categoryKey, $this->expandedCategories, true)) {
            $this->expandedCategories = array_values(array_filter(
                $this->expandedCategories,
                fn (string $key): bool => $key !== $categoryKey,
            ));
        } else {
            $this->expandedCategories[] = $categoryKey;
        }
    }

    public function expandAll(): void
    {
        $this->expandedCategories = array_keys($this->getGroupedPermissions());
    }

    public function collapseAll(): void
    {
        $this->expandedCategories = [];
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return PermissionMatrixData::roles();
    }

    /**
     * @return array<string, array{label: string, permissions: list<array{name: string, label: string}>}>
     */
    public function getGroupedPermissions(): array
    {
        return PermissionMatrixData::groupedPermissions($this->search);
    }

    public function roleHas(int $roleId, string $permission): bool
    {
        return PermissionMatrixData::roleHas($this->rolePermissions, $roleId, $permission);
    }

    public function canEditRole(Role $role): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if ($role->name === 'super-admin' && ! $user->hasRole('super-admin')) {
            return false;
        }

        if ($role->name === 'isp-admin' && ! $user->hasAnyRole(['super-admin', 'isp-admin'])) {
            return false;
        }

        return true;
    }

    protected function reloadRolePermissions(): void
    {
        $this->rolePermissions = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [
                $role->id => $role->permissions->pluck('name')->flip()->all(),
            ])
            ->all();
    }
}
