@props([
    'group',
    'skipSections' => ['Hub'],
])

@php
    $sections = \App\Support\AdminModuleRegistry::groupedBySection($group);
@endphp

@foreach ($sections as $sectionName => $items)
    @if (! in_array($sectionName, $skipSections, true))
        <section class="isp-ops-section">
            <h3 class="isp-ops-section-title">{{ $sectionName }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $mod)
                    <a href="{{ $mod['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon {{ $mod['accent'] }}">
                                <x-filament::icon :icon="\App\Support\AdminModuleRegistry::iconForModule($mod)" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $mod['label'] }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mod['description'] }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endforeach
