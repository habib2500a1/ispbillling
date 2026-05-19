@props([
    'group' => null,
    'hubUrl' => null,
    'hubLabel' => 'Hub home',
    'skipSections' => ['Hub'],
])

@php
    $sections = $group
        ? \App\Support\AdminModuleRegistry::groupedBySection($group)
        : [];
    foreach ($skipSections as $skip) {
        unset($sections[$skip]);
    }
@endphp

@if (count($sections) > 0)
    <nav class="isp-section-nav" aria-label="Section menu">
        @if ($hubUrl)
            <a href="{{ $hubUrl }}" class="isp-section-nav-hub">
                <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                {{ $hubLabel }}
            </a>
        @endif

        @foreach ($sections as $sectionName => $items)
            <div class="isp-section-nav-block">
                <p class="isp-section-nav-title">{{ $sectionName }}</p>
                <div class="isp-section-nav-grid">
                    @foreach ($items as $mod)
                        <a href="{{ $mod['url'] }}" class="isp-section-nav-item {{ request()->fullUrlIs($mod['url'].'*') || request()->url() === $mod['url'] ? 'isp-section-nav-item--active' : '' }}">
                            <x-filament::icon :icon="\App\Support\AdminModuleRegistry::iconForModule($mod)" class="h-4 w-4 shrink-0 opacity-80" />
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $mod['label'] }}</span>
                                <span class="block truncate text-[10px] opacity-70">{{ $mod['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
@endif
