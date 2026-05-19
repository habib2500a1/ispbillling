<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            title="Dashboard hub"
            description="Role-based real-time views for NOC, billing, GPON, MikroTik, support, and management. KPIs auto-refresh every 20–30 seconds."
            class="isp-hub-hero--indigo"
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->getDashboardCards() as $card)
                @if ($card['visible'])
                    <a href="{{ $card['url'] }}" class="isp-dash-hub-card isp-dash-hub-card--{{ $card['tone'] }}">
                        <span class="isp-dash-hub-card__icon">
                            <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="isp-dash-hub-card__title">{{ $card['title'] }}</h3>
                        <p class="isp-dash-hub-card__desc">{{ $card['description'] }}</p>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
