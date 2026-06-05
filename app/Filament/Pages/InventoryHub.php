<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Pages\InventoryDamagedMissingReport;
use App\Filament\Pages\InventoryLoansOverdueReport;
use App\Filament\Pages\InventorySupportDevicesOutReport;
use App\Filament\Pages\InventoryWarrantyManagement;
use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\InventorySaleResource;
use App\Filament\Resources\StoreDeviceLoanResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WarehouseResource;
use App\Services\Inventory\InventoryDashboardService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class InventoryHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.inventory-hub';

    protected static ?string $navigationLabel = 'Inventory center';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?int $navigationSort = 0;

    /** @var array<string, mixed> */
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = app(InventoryDashboardService::class)->summary();
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
    public function getKpiCards(): array
    {
        $s = $this->summary;
        $fmt = static fn (float $n): string => number_format($n, 0);

        return [
            [
                'label' => 'Stock value',
                'value' => $fmt((float) ($s['stock_value'] ?? 0)).' BDT',
                'hint' => $fmt((float) ($s['stock_units'] ?? 0)).' units · '.($s['product_count'] ?? 0).' products',
                'url' => ProductResource::getUrl(),
                'tone' => 'teal',
                'icon' => 'heroicon-o-cube',
            ],
            [
                'label' => 'Month sales',
                'value' => $fmt((float) ($s['month_sales'] ?? 0)).' BDT',
                'hint' => 'Profit '.$fmt((float) ($s['month_profit'] ?? 0)).' BDT',
                'url' => InventorySaleResource::getUrl(),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Low stock',
                'value' => (string) ($s['low_stock_count'] ?? 0),
                'hint' => 'At or below reorder level',
                'url' => ProductResource::getUrl(),
                'tone' => 'amber',
                'icon' => 'heroicon-o-exclamation-triangle',
                'alert' => ($s['low_stock_count'] ?? 0) > 0,
            ],
            [
                'label' => 'Open POs',
                'value' => (string) ($s['open_po_count'] ?? 0),
                'hint' => 'Draft or ordered',
                'url' => PurchaseOrderResource::getUrl(),
                'tone' => 'orange',
                'icon' => 'heroicon-o-clipboard-document-check',
            ],
            [
                'label' => 'Public shop',
                'value' => (string) ($s['shop_products'] ?? 0).' items',
                'hint' => 'Live on storefront',
                'url' => $this->getShopUrl(),
                'tone' => 'sky',
                'icon' => 'heroicon-o-globe-alt',
                'external' => true,
            ],
            [
                'label' => 'Support out',
                'value' => (string) ($s['support_out_count'] ?? 0),
                'hint' => 'Overdue '.($s['loans_overdue'] ?? 0).' · due today '.($s['loans_due_today'] ?? 0),
                'url' => InventorySupportDevicesOutReport::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-arrow-up-tray',
            ],
            [
                'label' => 'Damaged / missing',
                'value' => (string) ($s['damaged_missing_count'] ?? 0),
                'hint' => 'Products with write-offs',
                'url' => InventoryDamagedMissingReport::getUrl(),
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-triangle',
                'alert' => ($s['damaged_missing_count'] ?? 0) > 0,
            ],
            [
                'label' => 'Overdue loans',
                'value' => (string) ($s['loans_overdue'] ?? 0),
                'hint' => 'Past due return date',
                'url' => InventoryLoansOverdueReport::getUrl(),
                'tone' => 'red',
                'icon' => 'heroicon-o-clock',
                'alert' => ($s['loans_overdue'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool, external?: bool}>
     */
    public function getActionCards(): array
    {
        return [
            [
                'title' => 'New sale (POS)',
                'desc' => 'Barcode scan · warehouse · staff wallet · instant receipt.',
                'url' => InventorySaleResource::getUrl('create'),
                'icon' => 'heroicon-o-qr-code',
                'tone' => 'orange',
                'featured' => true,
            ],
            [
                'title' => 'Warehouses',
                'desc' => 'Multi-location stock · transfer between sites.',
                'url' => WarehouseResource::getUrl(),
                'icon' => 'heroicon-o-building-library',
                'tone' => 'amber',
            ],
            [
                'title' => 'New product',
                'desc' => 'Barcode · SKU · buy/sell · opening warehouse stock.',
                'url' => ProductResource::getUrl('create'),
                'icon' => 'heroicon-o-plus-circle',
                'tone' => 'teal',
            ],
            [
                'title' => 'All products',
                'desc' => 'Search · adjust stock · low-stock alerts.',
                'url' => ProductResource::getUrl(),
                'icon' => 'heroicon-o-shopping-bag',
                'tone' => 'teal',
            ],
            [
                'title' => 'Retail sales',
                'desc' => 'POS history · reprint · profit per sale.',
                'url' => InventorySaleResource::getUrl(),
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Purchase orders',
                'desc' => 'Receive into warehouse · accounts payable.',
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
                'title' => 'Invoices · hardware',
                'desc' => 'Add CPE line · link device · issue stock.',
                'url' => InvoiceResource::getUrl(),
                'icon' => 'heroicon-o-cpu-chip',
                'tone' => 'sky',
            ],
            [
                'title' => 'Devices / ONU',
                'desc' => 'CPE & network equipment inventory.',
                'url' => DeviceResource::getUrl(),
                'icon' => 'heroicon-o-wifi',
                'tone' => 'slate',
            ],
            [
                'title' => 'Support device loans',
                'desc' => 'Issue / return CPE for support visits.',
                'url' => StoreDeviceLoanResource::getUrl(),
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'tone' => 'violet',
            ],
            [
                'title' => 'Warranty management',
                'desc' => 'Expiry dates · vendor · claims.',
                'url' => InventoryWarrantyManagement::getUrl(),
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'sky',
            ],
            [
                'title' => 'Overdue returns',
                'desc' => 'Support devices past due date.',
                'url' => InventoryLoansOverdueReport::getUrl(),
                'icon' => 'heroicon-o-clock',
                'tone' => 'red',
            ],
            [
                'title' => 'Damaged / missing',
                'desc' => 'Stock write-offs and loss tracking.',
                'url' => InventoryDamagedMissingReport::getUrl(),
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => 'rose',
            ],
            [
                'title' => 'Vendors',
                'desc' => 'Suppliers · pay bills · purchase history.',
                'url' => VendorResource::getUrl(),
                'icon' => 'heroicon-o-building-storefront',
                'tone' => 'rose',
            ],
            [
                'title' => 'Collector settlement',
                'desc' => 'Transfer field staff cash to admin.',
                'url' => CollectorCashHub::getUrl(),
                'icon' => 'heroicon-o-wallet',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Public shop',
                'desc' => 'Customer-facing product catalog (new tab).',
                'url' => $this->getShopUrl(),
                'icon' => 'heroicon-o-globe-alt',
                'tone' => 'orange',
                'external' => true,
            ],
            [
                'title' => 'Accounting · COGS',
                'desc' => 'P&L includes COGS 5050 + retail 4050.',
                'url' => AccountingHub::getUrl(),
                'icon' => 'heroicon-o-calculator',
                'tone' => 'violet',
            ],
        ];
    }
}
