<x-filament-widgets::widget>
    <div
        id="isp-today-snapshot-root"
        class="isp-today-snapshot-wi"
        wire:poll.visible.120s
    >
        <div class="isp-today-strip" role="group" aria-label="Today at a glance">
            @foreach ($tiles as $tile)
                @if ($tile['url'])
                    <a href="{{ $tile['url'] }}" @class(['isp-today-tile', 'isp-today-tile--' . $tile['tone']])>
                        <span class="isp-today-tile__icon" aria-hidden="true">
                            <x-filament::icon :icon="$tile['icon']" class="isp-today-tile__icon-svg h-6 w-6" />
                        </span>
                        <span class="isp-today-tile__body">
                            <span class="isp-today-tile__value">{{ $tile['value'] }}</span>
                            <span class="isp-today-tile__label">{{ $tile['label'] }}</span>
                        </span>
                    </a>
                @else
                    <div @class(['isp-today-tile', 'isp-today-tile--' . $tile['tone']])>
                        <span class="isp-today-tile__icon" aria-hidden="true">
                            <x-filament::icon :icon="$tile['icon']" class="isp-today-tile__icon-svg h-6 w-6" />
                        </span>
                        <span class="isp-today-tile__body">
                            <span class="isp-today-tile__value">{{ $tile['value'] }}</span>
                            <span class="isp-today-tile__label">{{ $tile['label'] }}</span>
                        </span>
                    </div>
                @endif
            @endforeach
        </div>

        @if (($legacy_portal ?? false) && ($collected_today_raw ?? 0) <= 0.009)
            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>No local payments recorded today. Collections on {{ $portal_label }} sync every {{ (int) config('legacy_portal.collections_sync_every_minutes', 15) }} minutes.</span>
                <button type="button" wire:click="syncLegacyCollections" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Sync now
                </button>
                @if ($staff_performance_url)
                    <a href="{{ $staff_performance_url }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Staff performance →</a>
                @endif
            </p>
        @endif
    </div>
</x-filament-widgets::widget>
