<x-filament-panels::page>
    @php
        $cards = collect($this->getDashboardCards())->filter(fn ($card) => $card['visible']);
    @endphp

    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Role dashboards"
            title="Dashboard hub"
            description="Role-based real-time views for NOC, billing, GPON, MikroTik, support, and management. KPIs auto-refresh every 20–30 seconds."
            class="isp-hub-hero--indigo"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $cards->count() }} dashboards available</span>
                    <span class="isp-hub-section__meta">Auto refresh ready</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Role workspaces</h2>
                    <p class="isp-hub-section__desc">Choose a focused operations view for network, billing, support, security, or analytics.</p>
                </div>
                <span class="isp-hub-section__meta">Live teams</span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $card)
                    <a href="{{ $card['url'] }}" class="isp-dash-hub-card isp-dash-hub-card--{{ $card['tone'] }}">
                        <span class="isp-dash-hub-card__icon">
                            <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                        </span>
                        <span class="isp-dash-hub-card__eyebrow">{{ ucfirst($card['tone']) }} desk</span>
                        <h3 class="isp-dash-hub-card__title">{{ $card['title'] }}</h3>
                        <p class="isp-dash-hub-card__desc">{{ $card['description'] }}</p>
                        <span class="isp-dash-hub-card__arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
