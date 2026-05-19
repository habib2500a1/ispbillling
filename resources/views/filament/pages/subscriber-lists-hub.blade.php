<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero title="Subscriber lists" description="Quick access to filtered subscriber views — free, VIP, expired, suspended, and left clients.">
            <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('index') }}" class="mt-3 inline-flex text-sm font-semibold text-teal-600 hover:underline dark:text-teal-400">← All subscribers</a>
        </x-isp.hub-hero>

        <div class="isp-list-grid">
            @foreach ($this->getLists() as $list)
                <a href="{{ $list['url'] }}" class="isp-list-card isp-list-card--{{ $list['color'] }}">
                    <span class="isp-list-card-icon">
                        <x-filament::icon :icon="$list['icon']" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $list['label'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $list['description'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        <x-isp.hub-footer :links="[
            ['url' => \App\Filament\Resources\CustomerResource::getUrl('index'), 'label' => 'Subs', 'icon' => 'heroicon-o-users'],
            ['url' => \App\Filament\Pages\OperationsHub::getUrl(), 'label' => 'Modules', 'icon' => 'heroicon-o-squares-2x2'],
            ['url' => \App\Filament\Pages\BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-document-text'],
            ['url' => \App\Filament\Pages\SupportHub::getUrl(), 'label' => 'Support', 'icon' => 'heroicon-o-lifebuoy'],
            ['url' => \App\Filament\Pages\ReportsHub::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-pie'],
        ]" />
    </div>
</x-filament-panels::page>
