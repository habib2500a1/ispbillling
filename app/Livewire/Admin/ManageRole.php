<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ManageRole extends Component
{
    use WithoutUrlPagination, WithPagination;

    public $roleType;

    public $name;

    public $search;

    /** @var array<int, array<string, mixed>> */
    public $groupedPermissions = [];

    public $permissions = [];

    public $roleId;

    public $role;

    public $perPage = 10;

    public $confirmingRole = false;

    protected $listeners = ['roleEdit' => 'editRole', 'roleDelete' => 'deleteRole'];

    public function mount()
    {
        if (! hasAccess(['Super Admin'], ['create-user-role', 'edit-user-role', 'delete-user-role'])) {
            abort(403, 'Unauthorized Access.');
        }
    }

    public function newRole()
    {
        if (abortIfNoAccess(['Super Admin'], ['create-user-role'], 'You do not have permission to create roles.')) {
            return;
        }

        $this->reset(['roleType', 'roleId', 'name', 'permissions']);
        $this->loadPermissionGroups();
        $this->roleType = 'Create New Role';
        $this->confirmingRole = true;
    }

    public function editRole($roleId)
    {
        if (abortIfNoAccess(['Super Admin'], ['edit-user-role'], 'You do not have permission to edit roles.')) {
            return;
        }

        $this->role = Role::find($roleId);
        if (! $this->role) {
            session()->flash('error', 'Role not found.');

            return;
        }

        if ($this->role->name === 'Super Admin') {
            session()->flash('error', 'Super Admin role cannot be edited.');

            return;
        }

        $this->roleType = 'Edit Role';
        $this->roleId = $roleId;
        $this->name = $this->role->name;
        $this->permissions = $this->role->permissions->pluck('id')->map(fn ($id) => (int) $id)->toArray();
        $this->loadPermissionGroups();
        $this->confirmingRole = true;
    }

    public function saveRole()
    {
        if (abortIfNoAccess(['Super Admin'], ['edit-user-role'], 'You do not have permission to save roles.')) {
            return;
        }

        $this->validate([
            'name' => 'required|unique:roles,name,'.($this->roleId ?? 'NULL').'|max:255',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $permissionModels = Permission::query()
            ->whereIn('id', collect($this->permissions ?? [])->map(fn ($id) => (int) $id)->filter()->all())
            ->get();

        try {
            if ($this->roleId) {
                $role = Role::find($this->roleId);
                if (! $role) {
                    flash()->error('Role not found.');

                    return;
                }
                if ($role->name === 'Super Admin') {
                    flash()->error('Super Admin role cannot be edited.');

                    return;
                }
                $role->name = $this->name;
                $role->syncPermissions($permissionModels);
                $role->save();
                flash()->success('Role updated successfully.');
            } else {
                $role = Role::create(['guard_name' => 'web', 'name' => $this->name]);
                $role->syncPermissions($permissionModels);
                flash()->success('Role created successfully.');
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->confirmingRole = false;
        } catch (\Throwable $e) {
            report($e);
            flash()->error(__('Could not save role permissions. Please try again.'));
        }
    }

    public function deleteRole($roleId, $roleName)
    {
        if (abortIfNoAccess(['Super Admin'], ['delete-user-role'], 'You do not have permission to delete roles.')) {
            return;
        }
        if ($roleName === 'Super Admin') {
            session()->flash('error', 'Super Admin role cannot be deleted.');

            return;
        }
        $this->roleId = $roleId;
        sweetalert()
            ->option('confirmButtonText', 'Yes')
            ->showDenyButton()
            ->warning(
                "Are you sure you want to delete this '{$roleName}' role?",
                ['title' => 'Confirm Deletion']
            );
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload = []): void
    {
        try {
            if (! $this->roleId) {
                flash()->error('No role selected for deletion.');

                return;
            }

            $role = Role::find($this->roleId);

            if (! $role) {
                flash()->error('Role not found.');
                $this->roleId = null;

                return;
            }

            if ($role->name === 'Super Admin') {
                flash()->error('Super Admin role cannot be deleted.');
                $this->roleId = null;

                return;
            }

            $role->delete();
            $this->roleId = null;
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            flash()->success('Role successfully deleted.');
        } catch (\Throwable $e) {
            report($e);
            flash()->error(__('Could not delete role. It may still be assigned to users.'));
        }
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        flash()->info('Deletion cancelled.');
    }

    private function loadPermissionGroups(): void
    {
        $grouped = [];
        foreach (Permission::query()->orderBy('name')->get(['id', 'name']) as $permission) {
            $name = $permission->name;
            if (str_contains($name, 'user') && ! str_contains($name, 'role')) {
                $category = 'User Management';
            } elseif (str_contains($name, 'role')) {
                $category = 'Role & Permission';
            } elseif (str_contains($name, 'router') || str_contains($name, 'interface') || str_contains($name, 'traffic')) {
                $category = 'Router Management';
            } elseif (str_contains($name, 'hotspot') || str_contains($name, 'profile')) {
                $category = 'Hotspot Settings';
            } elseif (str_contains($name, 'reseller')) {
                $category = 'Reseller Management';
            } elseif (str_contains($name, 'payment') || str_contains($name, 'billing') || str_contains($name, 'collection')) {
                $category = 'Billing & Payments';
            } elseif (str_contains($name, 'customer') || str_contains($name, 'package')) {
                $category = 'Customer Management';
            } elseif (str_contains($name, 'olt') || str_contains($name, 'onu') || str_contains($name, 'optical')) {
                $category = 'Optical Network';
            } else {
                $category = 'Other Permissions';
            }
            $grouped[$category][] = [
                'id' => (int) $permission->id,
                'name' => $permission->name,
                'label' => ucwords(str_replace(['-', 'user', 'role', 'router', 'hotspot'], [' ', 'User', 'Role', 'Router', 'Hotspot'], $permission->name)),
            ];
        }
        $this->groupedPermissions = $grouped;
    }

    public function render()
    {
        $roles = Role::where('name', '!=', 'Reseller')->orderBy('id')->paginate($this->perPage);

        return view('livewire.admin.role.manage-role', ['roles' => $roles])->layout('layouts.app');
    }
}
