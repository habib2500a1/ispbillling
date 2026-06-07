@php
    $sectionTabs = collect($sections)->mapWithKeys(fn (array $section, int $i): array => [
        'tab-' . $i => $section['title'] ?? 'Section',
    ])->all();
    $firstTab = array_key_first($sectionTabs) ?? 'tab-0';
@endphp

<div
    class="isp-cmd-sections isp-cmd-sections--v2"
    x-data="{ sectionTab: @js($firstTab) }"
>
    @if (count($sections) > 1)
        <nav class="isp-cmd-sections__tabs" aria-label="Operations sections">
            @foreach ($sections as $i => $section)
                @php $tabId = 'tab-' . $i; @endphp
                <button
                    type="button"
                    class="isp-cmd-sections__tab"
                    :class="{ 'isp-cmd-sections__tab--active': sectionTab === @js($tabId) }"
                    @click="sectionTab = @js($tabId)"
                >
                    {{ $section['title'] }}
                </button>
            @endforeach
        </nav>
    @endif

    @foreach ($sections as $i => $section)
        @php $tabId = 'tab-' . $i; @endphp
        <section
            @class(['isp-cmd-section', 'isp-cmd-section--' . ($section['accent'] ?? 'teal')])
            x-show="sectionTab === @js($tabId) || {{ count($sections) <= 1 ? 'true' : 'false' }}"
            x-cloak
        >
            <header class="isp-cmd-section__title isp-cmd-section__title--desktop">
                <span class="isp-cmd-section__icon">
                    <x-filament::icon :icon="$section['icon']" class="h-4 w-4" />
                </span>
                <span class="isp-cmd-section__name">{{ $section['title'] }}</span>
                <span class="isp-cmd-section__count">{{ count($section['cards'] ?? []) }}</span>
            </header>
            <div class="isp-cmd-section__grid">
                @foreach ($section['cards'] as $card)
                    <a
                        href="{{ $card['url'] ?? '#' }}"
                        @class([
                            'isp-cmd-metric',
                            'isp-cmd-metric--' . ($card['tone'] ?? 'slate'),
                            'isp-cmd-metric--static' => empty($card['url']),
                        ])
                    >
                        <span class="isp-cmd-metric__value">{{ $card['value'] }}</span>
                        <span class="isp-cmd-metric__label">{{ $card['label'] }}</span>
                        @if (! empty($card['url']))
                            <x-heroicon-m-chevron-right class="isp-cmd-metric__arrow" />
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
