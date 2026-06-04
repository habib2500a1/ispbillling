<?php

namespace App\Http\Controllers\Concerns;

use App\Models\InventorySale;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Auth;

trait AuthorizesInventorySaleAccess
{
    protected function authorizeInventorySale(InventorySale $sale): InventorySale
    {
        abort_unless(Auth::check(), 401);
        abort_unless(StaffCapability::for(Auth::user())->canInventory(), 403);
        abort_unless((int) $sale->tenant_id === TenantResolver::requiredTenantId(), 404);

        return $sale->load(['items.product', 'warehouse', 'recorder']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function saleReceiptViewData(InventorySale $sale): array
    {
        return [
            'sale' => $sale,
            'company' => \App\Support\CompanyBranding::name(),
            'companyPhone' => \App\Support\CompanyBranding::phone(),
            'companyAddress' => \App\Support\CompanyBranding::address(),
            'logoUrl' => \App\Support\CompanyBranding::logoUrl(),
            'backUrl' => \App\Filament\Resources\InventorySaleResource::getUrl('view', ['record' => $sale]),
        ];
    }
}
