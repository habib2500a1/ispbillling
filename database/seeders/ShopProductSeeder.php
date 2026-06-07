<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ShopProductSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (int) (Tenant::query()->value('id') ?? config('inventory.default_tenant_id', 1));

        $products = [
            [
                'sku' => 'ONU-GPON-01',
                'name' => 'GPON ONU (1G)',
                'description' => 'Dual-band GPON ONU for home and business fiber connections.',
                'sell_price' => 1200,
                'cost_price' => 950,
                'stock_qty' => 50,
            ],
            [
                'sku' => 'ROUTER-WIFI-04',
                'name' => 'WiFi Router 4-Port',
                'description' => 'Home router with 4 LAN ports and PPPoE support.',
                'sell_price' => 2500,
                'cost_price' => 2100,
                'stock_qty' => 30,
            ],
            [
                'sku' => 'CABLE-CAT6-305',
                'name' => 'CAT6 Cable Box (305m)',
                'description' => 'Outdoor-rated CAT6 cable for ISP installations.',
                'sell_price' => 4500,
                'cost_price' => 3800,
                'stock_qty' => 15,
            ],
            [
                'sku' => 'PATCH-SC-3M',
                'name' => 'Fiber Patch Cord SC/UPC 3m',
                'description' => 'Single-mode patch cord for OLT/ONU links.',
                'sell_price' => 150,
                'cost_price' => 90,
                'stock_qty' => 100,
            ],
            [
                'sku' => 'SFP-1G-BIDI',
                'name' => '1G BiDi SFP Module',
                'description' => 'Compatible SFP for MikroTik and OLT uplinks.',
                'sell_price' => 800,
                'cost_price' => 620,
                'stock_qty' => 25,
            ],
        ];

        foreach ($products as $row) {
            Product::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'sku' => $row['sku'],
                ],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'unit' => 'pcs',
                    'unit_price' => $row['sell_price'],
                    'sell_price' => $row['sell_price'],
                    'cost_price' => $row['cost_price'],
                    'stock_qty' => $row['stock_qty'],
                    'is_active' => true,
                    'show_on_shop' => true,
                ],
            );
        }
    }
}
