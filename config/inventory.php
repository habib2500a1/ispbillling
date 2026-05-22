<?php

return [
    'default_tenant_id' => (int) env('INVENTORY_SHOP_TENANT_ID', 1),

    'default_warehouse_code' => env('INVENTORY_DEFAULT_WAREHOUSE_CODE', 'MAIN'),

    'shop_enabled' => (bool) env('INVENTORY_SHOP_ENABLED', true),

    'auto_post_purchase_receive' => (bool) env('INVENTORY_AUTO_POST_PO_RECEIVE', true),

    'auto_post_retail_sale' => (bool) env('INVENTORY_AUTO_POST_RETAIL_SALE', true),

    'inventory_asset_code' => env('INVENTORY_ASSET_CODE', '1300'),

    'ap_account_code' => env('INVENTORY_AP_CODE', '2000'),

    'retail_revenue_code' => env('INVENTORY_RETAIL_REVENUE_CODE', '4050'),

    'cogs_account_code' => env('INVENTORY_COGS_CODE', '5050'),

    'cash_account_code' => env('INVENTORY_CASH_CODE', '1000'),

    /** Staff cash sales go to collector wallet (1050) until admin settlement transfer. */
    'staff_sale_to_collector_wallet' => (bool) env('INVENTORY_STAFF_SALE_COLLECTOR_WALLET', true),

    /** Payment methods treated as cash-in-hand for field staff (same as bill collection). */
    'staff_collector_cash_methods' => ['cash', 'counter'],
];
