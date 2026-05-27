@php
    $lists = $this->getLists();
    $statCards = [
        ['label' => 'Saved views', 'value' => (string) count($lists), 'hint' => 'Subscriber segments ready', 'class' => 'isp-hub-stat--teal'],
        ['label' => 'Attention views', 'value' => '3', 'hint' => 'Expired, suspended, left', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
        ['label' => 'Growth views', 'value' => '2', 'hint' => 'Active and VIP monitoring', 'class' => 'isp-hub-stat--amber'],
        ['label' => 'Ops access', 'value' => 'Instant', 'hint' => 'One-click filtered lists', 'class' => 'isp-hub-stat--sky'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Subscriber operations"
            title="Subscriber lists"
            description="Quick access to filtered subscriber views for free, VIP, expired, suspended, and left clients."
            class="isp-hub-hero--teal"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ count($lists) }} saved views</span>
                    <span class="isp-hub-section__meta">Ops-ready filters</span>
                </div>
                <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('index') }}" class="isp-quick-pill">All subscribers</a>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Segment shortcuts</h2>
                    <p class="isp-hub-section__desc">Open the most-used subscriber filters for billing review, retention work, and support follow-up.</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($lists) }} lists</span>
            </div>
            <div class="isp-list-grid">
                @foreach ($lists as $list)
                    <a href="{{ $list['url'] }}" class="isp-list-card isp-list-card--{{ $list['color'] }}">
                        <span class="isp-list-card-icon">
                            <x-filament::icon :icon="$list['icon']" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="isp-list-card__eyebrow">Filtered subscribers</p>
                            <p class="isp-list-card__title">{{ $list['label'] }}</p>
                            <p class="isp-list-card__desc">{{ $list['description'] }}</p>
                        </div>
                        <span class="isp-list-card__arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </section>

        <x-isp.hub-footer :links="[
            ['url' => \App\Filament\Resources\CustomerResource::getUrl('index'), 'label' => 'Subs', 'icon' => 'heroicon-o-users'],
            ['url' => \App\Filament\Pages\OperationsHub::getUrl(), 'label' => 'Modules', 'icon' => 'heroicon-o-squares-2x2'],
            ['url' => \App\Filament\Pages\BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-document-text'],
            ['url' => \App\Filament\Pages\SupportHub::getUrl(), 'label' => 'Support', 'icon' => 'heroicon-o-lifebuoy'],
            ['url' => \App\Filament\Pages\ReportsHub::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-pie'],
        ]" />
    </div>
</x-filament-panels::page>
