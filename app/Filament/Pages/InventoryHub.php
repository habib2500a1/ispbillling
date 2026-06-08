<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Pages\InventoryDamagedMissingReport;
use App\Filament\Pages\InventoryLoansOverdueReport;
use App\Filament\Pages\InventorySupportDevicesOutReport;
use App\Filament\Pages\InventoryWarrantyManagement;
use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\FixedAssetResource;
use App\Filament\Resources\InventorySaleResource;
use App\Filament\Resources\PopBoxResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StoreDeviceLoanResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WarehouseResource;
use App\Services\Inventory\InventoryAssetIntelligenceService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class InventoryHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.inventory-hub';

    protected static ?string $navigationLabel = 'Asset intelligence';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?int $navigationSort = 0;

    /** @var array<string, mixed> */
    public array $metrics = [];

    public function mount(): void
    {
        $this->metrics = app(InventoryAssetIntelligenceService::class)->metrics();
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-inventory-module isp-inventory-hub-page',
        ];
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canInventory();
    }

    public function getShopUrl(): string
    {
        return route('shop.index');
    }

    /**
     * @return list<array{label: string, value: string, hint: string, url: string, tone: string, icon: string, alert?: bool, external?: bool}>
     */
    public function getAssetDashboardCards(): array
    {
        $m = $this->metrics;
        $fmt = static fn (float|int $n): string => number_format((float) $n, 0);

        return [
            [
                'label' => 'Total assets',
                'value' => $fmt($m['total_assets'] ?? 0),
                'hint' => $fmt($m['total_devices'] ?? 0).' CPE · '.$fmt($m['total_fixed_assets'] ?? 0).' fixed',
                'url' => DeviceResource::getUrl(),
                'tone' => 'slate',
                'icon' => 'heroicon-o-squares-2x2',
            ],
            [
                'label' => 'Active assets',
                'value' => $fmt($m['active_assets'] ?? 0),
                'hint' => 'In service · in stock',
                'url' => DeviceResource::getUrl(),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-check-badge',
            ],
            [
                'label' => 'Assigned',
                'value' => $fmt($m['assigned_assets'] ?? 0),
                'hint' => 'Subscribers · loans · staff',
                'url' => StoreDeviceLoanResource::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-user-group',
            ],
            [
                'label' => 'Unassigned',
                'value' => $fmt($m['unassigned_assets'] ?? 0),
                'hint' => 'Available in warehouse',
                'url' => DeviceResource::getUrl(),
                'tone' => 'sky',
                'icon' => 'heroicon-o-archive-box',
            ],
            [
                'label' => 'Damaged',
                'value' => $fmt($m['damaged_assets'] ?? 0),
                'hint' => 'Faulty CPE · write-offs',
                'url' => InventoryDamagedMissingReport::getUrl(),
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-triangle',
                'alert' => ($m['damaged_assets'] ?? 0) > 0,
            ],
            [
                'label' => 'Low stock',
                'value' => $fmt($m['low_stock_count'] ?? 0),
                'hint' => 'At or below reorder level',
                'url' => ProductResource::getUrl(),
                'tone' => 'amber',
                'icon' => 'heroicon-o-arrow-trending-down',
                'alert' => ($m['low_stock_count'] ?? 0) > 0,
            ],
            [
                'label' => 'Pending purchases',
                'value' => $fmt($m['pending_purchases'] ?? 0),
                'hint' => 'Draft or ordered POs',
                'url' => PurchaseOrderResource::getUrl(),
                'tone' => 'orange',
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'Warranty expiring',
                'value' => $fmt($m['warranty_expiring'] ?? 0),
                'hint' => 'Next 30 days',
                'url' => InventoryWarrantyManagement::getUrl(),
                'tone' => 'red',
                'icon' => 'heroicon-o-shield-exclamation',
                'alert' => ($m['warranty_expiring'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string, hint: string, url: string, tone: string, icon: string, alert?: bool, external?: bool}>
     */
    public function getKpiCards(): array
    {
        $m = $this->metrics;
        $fmt = static fn (float $n): string => number_format($n, 0);

        return [
            [
                'label' => 'Stock value',
                'value' => $fmt((float) ($m['stock_value'] ?? 0)).' BDT',
                'hint' => $fmt((float) ($m['stock_units'] ?? 0)).' units · '.($m['product_count'] ?? 0).' SKUs',
                'url' => ProductResource::getUrl(),
                'tone' => 'teal',
                'icon' => 'heroicon-o-cube',
            ],
            [
                'label' => 'Month sales',
                'value' => $fmt((float) ($m['month_sales'] ?? 0)).' BDT',
                'hint' => 'Profit '.$fmt((float) ($m['month_profit'] ?? 0)).' BDT',
                'url' => InventorySaleResource::getUrl(),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Warehouses',
                'value' => (string) ($m['warehouse_count'] ?? 0),
                'hint' => 'Active locations',
                'url' => WarehouseResource::getUrl(),
                'tone' => 'cyan',
                'icon' => 'heroicon-o-building-library',
            ],
            [
                'label' => 'Support out',
                'value' => (string) ($m['support_out_count'] ?? 0),
                'hint' => 'Overdue '.($m['loans_overdue'] ?? 0).' · due today '.($m['loans_due_today'] ?? 0),
                'url' => InventorySupportDevicesOutReport::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-arrow-up-tray',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, icon: string}>
     */
    public function getAssetTypes(): array
    {
        return $this->metrics['asset_types'] ?? [];
    }

    /**
     * @return list<array{key: string, label: string, count: int, desc: string}>
     */
    public function getLifecycleStages(): array
    {
        return $this->metrics['lifecycle'] ?? [];
    }

    /**
     * @return list<array{label: string, count: int, tone: string, url: string}>
     */
    public function getSmartAlerts(): array
    {
        return $this->metrics['alerts'] ?? [];
    }

    /**
     * @return list<array{label: string, value: string, hint: string}>
     */
    public function getAnalyticsCards(): array
    {
        return $this->metrics['analytics'] ?? [];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool, external?: bool}>
     */
    public function getActionCards(): array
    {
        return [
            [
                'title' => 'QR / Barcode POS',
                'desc' => 'Scan · sell · warehouse · instant receipt.',
                'url' => InventorySaleResource::getUrl('create'),
                'icon' => 'heroicon-o-qr-code',
                'tone' => 'orange',
                'featured' => true,
            ],
            [
                'title' => 'Warehouses',
                'desc' => 'Multi-site stock · transfers · monitoring.',
                'url' => WarehouseResource::getUrl(),
                'icon' => 'heroicon-o-building-library',
                'tone' => 'amber',
            ],
            [
                'title' => 'Products & SKUs',
                'desc' => 'Barcode labels · reorder · shop catalog.',
                'url' => ProductResource::getUrl(),
                'icon' => 'heroicon-o-shopping-bag',
                'tone' => 'teal',
            ],
            [
                'title' => 'Purchase orders',
                'desc' => 'Vendor PO · receive · accounts payable.',
                'url' => PurchaseOrderResource::getUrl(),
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => 'violet',
            ],
            [
                'title' => 'Stock ledger',
                'desc' => 'Per-warehouse in/out audit trail.',
                'url' => StockMovementResource::getUrl(),
                'icon' => 'heroicon-o-arrow-path',
                'tone' => 'cyan',
            ],
            [
                'title' => 'Vendors',
                'desc' => 'Supplier profile · purchase history · warranty.',
                'url' => VendorResource::getUrl(),
                'icon' => 'heroicon-o-building-storefront',
                'tone' => 'rose',
            ],
            [
                'title' => 'Devices / ONU / OLT',
                'desc' => 'CPE registry · GIS link · NOC integration.',
                'url' => DeviceResource::getUrl(),
                'icon' => 'heroicon-o-wifi',
                'tone' => 'slate',
            ],
            [
                'title' => 'Technician loans',
                'desc' => 'Issue · return · responsibility chain.',
                'url' => StoreDeviceLoanResource::getUrl(),
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'tone' => 'violet',
            ],
            [
                'title' => 'Fixed assets',
                'desc' => 'Capital equipment · depreciation notes.',
                'url' => FixedAssetResource::getUrl(),
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'sky',
            ],
            [
                'title' => 'Warranty center',
                'desc' => 'Expiry alerts · vendor claims.',
                'url' => InventoryWarrantyManagement::getUrl(),
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'sky',
            ],
            [
                'title' => 'GIS / Fiber plant',
                'desc' => 'POP boxes · junction · map assets.',
                'url' => FiberPlantMap::getUrl(),
                'icon' => 'heroicon-o-map',
                'tone' => 'emerald',
            ],
            [
                'title' => 'POP / junction boxes',
                'desc' => 'Lat/lng · fiber plant nodes.',
                'url' => PopBoxResource::getUrl(),
                'icon' => 'heroicon-o-map-pin',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Invoice hardware',
                'desc' => 'CPE line · issue stock on bill.',
                'url' => InvoiceResource::getUrl(),
                'icon' => 'heroicon-o-cpu-chip',
                'tone' => 'orange',
            ],
            [
                'title' => 'Public shop',
                'desc' => 'Customer-facing catalog (new tab).',
                'url' => $this->getShopUrl(),
                'icon' => 'heroicon-o-globe-alt',
                'tone' => 'amber',
                'external' => true,
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string}>
     */
    public function getGisIntegrationLinks(): array
    {
        return [
            [
                'title' => 'Fiber plant map',
                'desc' => 'OLT · splitter · cable topology',
                'url' => FiberPlantMap::getUrl(),
                'icon' => 'heroicon-o-map',
            ],
            [
                'title' => 'Customer subscribers',
                'desc' => 'ONU · GPS · service address',
                'url' => \App\Filament\Resources\CustomerResource::getUrl(),
                'icon' => 'heroicon-o-users',
            ],
            [
                'title' => 'Network devices',
                'desc' => 'OLT · ONU · router registry',
                'url' => DeviceResource::getUrl(),
                'icon' => 'heroicon-o-server-stack',
            ],
            [
                'title' => 'NOC wall',
                'desc' => 'Live outages · zone impact',
                'url' => NocWall::getUrl(),
                'icon' => 'heroicon-o-signal',
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string}>
     */
    public function getQrBarcodeLinks(): array
    {
        return [
            [
                'title' => 'POS barcode scan',
                'desc' => 'Retail sale · warehouse pick',
                'url' => InventorySaleResource::getUrl('create'),
                'icon' => 'heroicon-o-qr-code',
            ],
            [
                'title' => 'Product labels',
                'desc' => 'SKU · barcode on catalog',
                'url' => ProductResource::getUrl(),
                'icon' => 'heroicon-o-tag',
            ],
            [
                'title' => 'Mobile field POS',
                'desc' => 'Technician smartphone scanning',
                'url' => InventorySaleResource::getUrl('create'),
                'icon' => 'heroicon-o-device-phone-mobile',
            ],
        ];
    }
}
