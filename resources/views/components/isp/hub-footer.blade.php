@props([
    'links' => null,
])

@php
    $items = $links ?? [
        ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
        ['url' => \App\Filament\Resources\CustomerResource::getUrl('index'), 'label' => 'Subs', 'icon' => 'heroicon-o-users'],
        ['url' => \App\Filament\Pages\OperationsHub::getUrl(), 'label' => 'Modules', 'icon' => 'heroicon-o-squares-2x2'],
        ['url' => \App\Filament\Pages\SupportHub::getUrl(), 'label' => 'Support', 'icon' => 'heroicon-o-lifebuoy'],
        ['url' => \App\Filament\Pages\ReportsHub::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-pie'],
    ];
@endphp

<footer class="isp-hub-footer" aria-label="Quick navigation">
    <div class="isp-hub-footer-inner">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" class="isp-hub-footer-link {{ request()->fullUrlIs($item['url'].'*') || request()->url() === $item['url'] ? 'isp-hub-footer-link--active' : '' }}">
                @if (! empty($item['icon']))
                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                @endif
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</footer>
