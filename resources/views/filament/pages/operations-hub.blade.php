<x-filament-panels::page>
    @php
        $modules = $this->getModules();
        $grouped = collect($modules)->groupBy('group');
        $groups = $grouped->keys()->all();
    @endphp

    <div
        class="isp-hub-page space-y-6"
        x-data="{
            q: '',
            group: 'All',
            matches(mod) {
                const hay = (mod.label + ' ' + mod.description + ' ' + mod.group + ' ' + (mod.section || '')).toLowerCase();
                const okGroup = this.group === 'All' || mod.group === this.group;
                const okQ = !this.q || hay.includes(this.q.toLowerCase());
                return okGroup && okQ;
            },
            visibleCount(items) {
                return items.filter((mod) => this.matches(mod)).length;
            }
        }"
    >
        <x-isp.hub-hero eyebrow="Admin workspace" title="Module directory" description="All modules grouped by department and section — search or filter below.">
            <div class="isp-hub-toolbar">
                <input type="search" x-model="q" placeholder="Search modules…" class="isp-dash-search max-w-md">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ count($modules) }} modules indexed</span>
                    <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="text-sm font-medium text-teal-600 hover:underline dark:text-teal-400">← Command center</a>
                </div>
            </div>
        </x-isp.hub-hero>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="group = 'All'" class="isp-dash-chip" :class="group === 'All' && 'isp-dash-chip-active'">All</button>
            @foreach ($groups as $g)
                <button type="button" @click="group = @js($g)" class="isp-dash-chip" :class="group === @js($g) && 'isp-dash-chip-active'">{{ $g }}</button>
            @endforeach
        </div>

        @foreach ($grouped as $groupName => $items)
            @php $bySection = $items->groupBy('section'); @endphp
            <div x-show="group === 'All' || group === @js($groupName)" x-cloak class="space-y-4">
                <div class="flex items-center gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                    <span class="isp-module-icon text-gray-600 dark:text-gray-300">
                        <x-filament::icon :icon="\App\Support\AdminModuleRegistry::iconForGroup($groupName)" class="h-5 w-5" />
                    </span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">{{ $groupName }}</h2>
                    <span class="text-xs text-gray-400">({{ $items->count() }})</span>
                </div>

                @foreach ($bySection as $sectionName => $sectionItems)
                    @php
                        $sectionPayload = $sectionItems->map(fn ($mod) => [
                            'label' => $mod['label'],
                            'description' => $mod['description'],
                            'group' => $mod['group'],
                            'section' => $mod['section'],
                        ])->values();
                    @endphp
                    <section class="isp-ops-section">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="isp-ops-section-title">{{ $sectionName }}</h3>
                            <span class="isp-hub-results" x-text="visibleCount(@js($sectionPayload)) + ' visible'"></span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($sectionItems as $mod)
                                <a
                                    href="{{ $mod['url'] }}"
                                    class="isp-module-card group"
                                    x-show="matches(@js(['label' => $mod['label'], 'description' => $mod['description'], 'group' => $mod['group'], 'section' => $mod['section']]))"
                                    x-cloak
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="isp-module-icon {{ $mod['accent'] }}">
                                            <x-filament::icon :icon="\App\Support\AdminModuleRegistry::iconForModule($mod)" class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="isp-module-card__eyebrow">{{ $sectionName }}</p>
                                            <p class="isp-module-card__title">{{ $mod['label'] }}</p>
                                            <p class="isp-module-card__desc">{{ $mod['description'] }}</p>
                                        </div>
                                        <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                        <div
                            x-show="visibleCount(@js($sectionPayload)) === 0"
                            x-cloak
                            class="isp-hub-empty"
                        >
                            No module matches the current search or filter in this section.
                        </div>
                    </section>
                @endforeach
            </div>
        @endforeach

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
