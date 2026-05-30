<x-filament-widgets::widget>
    @php
        $currency = fn ($v) => number_format((float) $v, 0).' ৳';
        $custIndex = \Illuminate\Support\Facades\Route::has('filament.admin.resources.customers.index')
            ? route('filament.admin.resources.customers.index') : null;
        $collectRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.bill-collection-desk')
            ? route('filament.admin.pages.bill-collection-desk') : $custIndex;
        $ticketRoute = \Illuminate\Support\Facades\Route::has('filament.admin.pages.support-hub')
            ? route('filament.admin.pages.support-hub') : null;

        $tiles = [
            [
                'label' => "Collected today",
                'value' => $currency($snapshot['collected_today'] ?? 0),
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'emerald',
                'url' => $collectRoute,
            ],
            [
                'label' => 'Due customers',
                'value' => number_format($snapshot['due_customers'] ?? 0),
                'icon' => 'heroicon-o-exclamation-circle',
                'tone' => ($snapshot['due_customers'] ?? 0) > 0 ? 'rose' : 'slate',
                'url' => $collectRoute,
            ],
            [
                'label' => 'Open tickets',
                'value' => number_format($snapshot['open_tickets'] ?? 0),
                'icon' => 'heroicon-o-lifebuoy',
                'tone' => ($snapshot['open_tickets'] ?? 0) > 0 ? 'amber' : 'slate',
                'url' => $ticketRoute,
            ],
            [
                'label' => 'Expiring today',
                'value' => number_format($snapshot['expiring_today'] ?? 0),
                'icon' => 'heroicon-o-clock',
                'tone' => ($snapshot['expiring_today'] ?? 0) > 0 ? 'rose' : 'slate',
                'url' => $custIndex,
            ],
            [
                'label' => 'Expiring tomorrow',
                'value' => number_format($snapshot['expiring_tomorrow'] ?? 0),
                'icon' => 'heroicon-o-calendar-days',
                'tone' => ($snapshot['expiring_tomorrow'] ?? 0) > 0 ? 'amber' : 'slate',
                'url' => $custIndex,
            ],
        ];
    @endphp

    <div class="isp-today-strip" role="group" aria-label="Today at a glance">
        @foreach ($tiles as $tile)
            @php($tag = $tile['url'] ? 'a' : 'div')
            <{{ $tag }}
                @if ($tile['url']) href="{{ $tile['url'] }}" @endif
                class="isp-today-tile isp-today-tile--{{ $tile['tone'] }}"
            >
                <span class="isp-today-tile__icon" aria-hidden="true">
                    <x-filament::icon :icon="$tile['icon']" class="isp-today-tile__icon-svg" />
                </span>
                <span class="isp-today-tile__body">
                    <span class="isp-today-tile__value">{{ $tile['value'] }}</span>
                    <span class="isp-today-tile__label">{{ $tile['label'] }}</span>
                </span>
            </{{ $tag }}>
        @endforeach
    </div>
</x-filament-widgets::widget>
