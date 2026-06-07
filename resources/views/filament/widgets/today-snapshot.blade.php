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
    </div>
</x-filament-widgets::widget>
