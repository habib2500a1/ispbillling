<x-filament-panels::page>
    <div class="isp-rbac-matrix-page space-y-4">
        <div class="isp-rbac-matrix-toolbar">
            <div class="isp-rbac-matrix-toolbar__intro">
                <h2 class="isp-rbac-matrix-toolbar__title">Permission matrix</h2>
                <p class="isp-rbac-matrix-toolbar__desc">
                    Grouped categories · click checkboxes to grant or revoke · changes are audit-logged.
                </p>
            </div>
            <div class="isp-rbac-matrix-toolbar__actions">
                <label class="isp-rbac-matrix-search">
                    <x-heroicon-m-magnifying-glass class="isp-rbac-matrix-search__icon" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search permission or key…"
                        class="isp-rbac-matrix-search__input"
                    />
                </label>
                <button type="button" wire:click="expandAll" class="isp-rbac-btn isp-rbac-btn--ghost">
                    Expand all
                </button>
                <button type="button" wire:click="collapseAll" class="isp-rbac-btn isp-rbac-btn--ghost">
                    Collapse all
                </button>
                <a href="{{ \App\Filament\Resources\RoleResource::getUrl('index') }}" class="isp-rbac-btn isp-rbac-btn--primary">
                    Manage roles
                </a>
            </div>
        </div>

        @if ($this->getGroupedPermissions() === [])
            <div class="isp-rbac-matrix-empty">
                No permissions match your search.
            </div>
        @else
            <div class="isp-rbac-matrix-wrap" wire:loading.class="isp-rbac-matrix-wrap--loading">
                <table class="isp-rbac-matrix" role="grid">
                    <thead>
                        <tr>
                            <th class="isp-rbac-matrix__corner" scope="col">
                                <span class="isp-rbac-matrix__corner-label">Permission</span>
                            </th>
                            @foreach ($this->getRoles() as $role)
                                <th
                                    scope="col"
                                    class="isp-rbac-matrix__role-col @if ($focusRole === $role->name) is-focus @endif"
                                    title="{{ \App\Support\Rbac\PermissionMatrixData::roleDescription($role) }}"
                                >
                                    <span class="isp-rbac-matrix__role-name">
                                        {{ \App\Support\Rbac\PermissionMatrixData::roleLabel($role) }}
                                    </span>
                                    <span class="isp-rbac-matrix__role-key">{{ $role->name }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getGroupedPermissions() as $categoryKey => $group)
                            <tr
                                class="isp-rbac-matrix__category-row"
                                wire:key="cat-{{ $categoryKey }}"
                            >
                                <td
                                    colspan="{{ $this->getRoles()->count() + 1 }}"
                                    class="isp-rbac-matrix__category-cell"
                                >
                                    <button
                                        type="button"
                                        class="isp-rbac-matrix__category-btn"
                                        wire:click="toggleCategory('{{ $categoryKey }}')"
                                        aria-expanded="{{ in_array($categoryKey, $expandedCategories, true) ? 'true' : 'false' }}"
                                    >
                                        <span class="isp-rbac-matrix__caret @if (in_array($categoryKey, $expandedCategories, true)) is-open @endif" aria-hidden="true"></span>
                                        <span class="isp-rbac-matrix__category-label">{{ $group['label'] }}</span>
                                        <span class="isp-rbac-matrix__category-count">{{ count($group['permissions']) }}</span>
                                    </button>
                                </td>
                            </tr>
                            @if (in_array($categoryKey, $expandedCategories, true))
                                @foreach ($group['permissions'] as $permission)
                                    <tr
                                        class="isp-rbac-matrix__perm-row"
                                        wire:key="perm-{{ $categoryKey }}-{{ $permission['name'] }}"
                                    >
                                        <th scope="row" class="isp-rbac-matrix__perm-cell">
                                            <span class="isp-rbac-matrix__perm-label">{{ $permission['label'] }}</span>
                                            <code class="isp-rbac-matrix__perm-key">{{ $permission['name'] }}</code>
                                        </th>
                                        @foreach ($this->getRoles() as $role)
                                            <td class="isp-rbac-matrix__check-cell @if ($focusRole === $role->name) is-focus @endif">
                                                <button
                                                    type="button"
                                                    class="isp-rbac-check
                                                        @if ($this->roleHas($role->id, $permission['name'])) is-checked @endif
                                                        @if (! $this->canEditRole($role)) is-locked @endif"
                                                    wire:click="togglePermission({{ $role->id }}, @js($permission['name']))"
                                                    @disabled(! $this->canEditRole($role))
                                                    aria-label="{{ $permission['label'] }} for {{ $role->name }}"
                                                    aria-pressed="{{ $this->roleHas($role->id, $permission['name']) ? 'true' : 'false' }}"
                                                >
                                                    @if ($this->roleHas($role->id, $permission['name']))
                                                        <x-heroicon-s-check class="isp-rbac-check__icon" />
                                                    @endif
                                                </button>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="isp-rbac-matrix-footnote">
            <x-heroicon-o-shield-check class="inline h-4 w-4 align-text-bottom" />
            Super Admin and ISP Admin roles are protected for non–super-admin users.
        </p>
    </div>
</x-filament-panels::page>
