@php
    $statePath = $getStatePath();
    $selected = array_fill_keys(array_map('strval', (array) ($getState() ?? [])), true);
    $groups = \App\Support\Rbac\PermissionMatrixData::groupedPermissions();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="isp-rbac-matrix isp-rbac-matrix--picker"
        x-data="{
            search: '',
            expanded: @js(array_keys($groups)),
            matches(label, key, category) {
                const q = this.search.trim().toLowerCase();
                if (!q) return true;
                return (category + ' ' + label + ' ' + key).toLowerCase().includes(q);
            },
            toggleCategory(key) {
                if (this.expanded.includes(key)) {
                    this.expanded = this.expanded.filter(k => k !== key);
                } else {
                    this.expanded.push(key);
                }
            }
        }"
    >
        <div class="isp-rbac-matrix-toolbar isp-rbac-matrix-toolbar--compact">
            <label class="isp-rbac-matrix-search">
                <x-heroicon-m-magnifying-glass class="isp-rbac-matrix-search__icon" />
                <input
                    type="search"
                    x-model="search"
                    placeholder="Search permissions…"
                    class="isp-rbac-matrix-search__input"
                />
            </label>
            <button type="button" class="isp-rbac-btn isp-rbac-btn--ghost" @click="expanded = @js(array_keys($groups))">
                Expand all
            </button>
            <button type="button" class="isp-rbac-btn isp-rbac-btn--ghost" @click="expanded = []">
                Collapse all
            </button>
        </div>

        <div class="isp-rbac-matrix-wrap isp-rbac-matrix-wrap--picker">
            <table class="isp-rbac-matrix">
                <thead>
                    <tr>
                        <th class="isp-rbac-matrix__corner" scope="col">Permission</th>
                        <th class="isp-rbac-matrix__role-col is-focus" scope="col">
                            <span class="isp-rbac-matrix__role-name">This role</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $categoryKey => $group)
                        <tr
                            class="isp-rbac-matrix__category-row"
                            x-show="@js($group['permissions']).some(p => matches(p.label, p.name, @js($group['label'])))"
                        >
                            <td colspan="2" class="isp-rbac-matrix__category-cell">
                                <button
                                    type="button"
                                    class="isp-rbac-matrix__category-btn"
                                    @click="toggleCategory('{{ $categoryKey }}')"
                                >
                                    <span
                                        class="isp-rbac-matrix__caret"
                                        :class="{ 'is-open': expanded.includes('{{ $categoryKey }}') }"
                                    ></span>
                                    <span class="isp-rbac-matrix__category-label">{{ $group['label'] }}</span>
                                    <span class="isp-rbac-matrix__category-count">{{ count($group['permissions']) }}</span>
                                </button>
                            </td>
                        </tr>
                        @foreach ($group['permissions'] as $permission)
                            @php
                                $isChecked = isset($selected[$permission['name']]);
                            @endphp
                            <tr
                                class="isp-rbac-matrix__perm-row"
                                x-show="expanded.includes('{{ $categoryKey }}') && matches(@js($permission['label']), @js($permission['name']), @js($group['label']))"
                                x-cloak
                            >
                                <th scope="row" class="isp-rbac-matrix__perm-cell">
                                    <span class="isp-rbac-matrix__perm-label">{{ $permission['label'] }}</span>
                                    <code class="isp-rbac-matrix__perm-key">{{ $permission['name'] }}</code>
                                </th>
                                <td class="isp-rbac-matrix__check-cell is-focus">
                                    <label class="isp-rbac-check-wrap">
                                        <input
                                            type="checkbox"
                                            class="isp-rbac-check-input"
                                            value="{{ $permission['name'] }}"
                                            wire:model.live="{{ $statePath }}"
                                        />
                                        <span class="isp-rbac-check @if ($isChecked) is-checked @endif">
                                            @if ($isChecked)
                                                <x-heroicon-s-check class="isp-rbac-check__icon" />
                                            @endif
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-dynamic-component>
