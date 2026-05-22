@php
    $s = $summary ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-isp.hub-hero
            title="Inventory Pro"
            description="Multi-warehouse · barcode POS · invoice hardware lines · buy/sell · stock · COGS + revenue."
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Stock value (cost)</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-teal-700 dark:text-teal-300">{{ $fmt($s['stock_value'] ?? 0) }} <span class="text-sm">BDT</span></p>
                <p class="mt-1 text-xs text-gray-500">{{ $fmt($s['stock_units'] ?? 0) }} units · {{ $s['product_count'] ?? 0 }} products</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Month retail sales</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $fmt($s['month_sales'] ?? 0) }} <span class="text-sm">BDT</span></p>
                <p class="mt-1 text-xs text-emerald-600">Profit {{ $fmt($s['month_profit'] ?? 0) }} BDT</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-950/30">
                <p class="text-xs font-bold uppercase text-amber-800 dark:text-amber-200">Low stock</p>
                <p class="mt-1 text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $s['low_stock_count'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-800/80">At or below reorder level</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Open POs · Shop</p>
                <p class="mt-1 text-2xl font-bold">{{ $s['open_po_count'] ?? 0 }} <span class="text-sm font-normal text-gray-500">PO</span></p>
                <a href="{{ $this->getShopUrl() }}" target="_blank" class="mt-2 inline-block text-xs font-semibold text-teal-600 hover:underline">
                    Public shop ({{ $s['shop_products'] ?? 0 }} items) →
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['Warehouses', 'Multi-location stock · transfer between sites', \App\Filament\Resources\WarehouseResource::getUrl()],
                ['Products', 'SKU · barcode · buy/sell · shop', \App\Filament\Resources\ProductResource::getUrl()],
                ['Retail sales (POS)', 'Barcode scan · warehouse · staff wallet', \App\Filament\Resources\InventorySaleResource::getUrl()],
                ['Collector settlement', 'Transfer staff cash to admin', \App\Filament\Pages\CollectorCashHub::getUrl()],
                ['Purchase orders', 'Receive into warehouse · AP', \App\Filament\Resources\PurchaseOrderResource::getUrl()],
                ['Stock ledger', 'Per-warehouse in/out', \App\Filament\Resources\StockMovementResource::getUrl()],
                ['Invoices', 'Add hardware line · link device · issue stock', \App\Filament\Resources\InvoiceResource::getUrl()],
                ['Devices / ONU', 'CPE network inventory', \App\Filament\Resources\DeviceResource::getUrl()],
                ['Vendors & payments', 'Suppliers · pay bills', \App\Filament\Resources\VendorResource::getUrl()],
                ['Accounting hub', 'P&L includes COGS 5050 + retail 4050', \App\Filament\Pages\AccountingHub::getUrl()],
                ['OLTs & POP', 'Fiber inventory', \App\Filament\Resources\OltResource::getUrl()],
            ] as [$title, $desc, $url])
                <a href="{{ $url }}" class="isp-hub-link group block">
                    <p class="font-semibold text-gray-900 group-hover:text-teal-600 dark:text-white">{{ $title }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $desc }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
