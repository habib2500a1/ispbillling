@php $link = 'inline-flex font-medium text-primary-600 hover:underline dark:text-primary-400'; @endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-isp.hub-hero
            title="Inventory & purchase"
            description="Devices, vendors, purchase bills — same workflow as ISP Digital inventory & purchase modules."
        />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['Products', 'SKU catalog & stock levels', \App\Filament\Resources\ProductResource::getUrl()],
                ['Purchase orders', 'PO create & receive stock', \App\Filament\Resources\PurchaseOrderResource::getUrl()],
                ['Devices / ONU stock', 'CPE & network equipment inventory', \App\Filament\Resources\DeviceResource::getUrl()],
                ['Vendors', 'Supplier list & purchase bills', \App\Filament\Resources\VendorResource::getUrl()],
                ['Vendor payments', 'Pay supplier invoices', \App\Filament\Resources\VendorPaymentResource::getUrl('index')],
                ['OLTs', 'PON ports & ONU sync', \App\Filament\Resources\OltResource::getUrl()],
                ['POP / boxes', 'POP sites & map coordinates', \App\Filament\Resources\PopBoxResource::getUrl()],
            ] as [$title, $desc, $url])
                <a href="{{ $url }}" class="isp-hub-link group block">
                    <p class="font-semibold text-gray-900 group-hover:text-teal-600 dark:text-white">{{ $title }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $desc }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
