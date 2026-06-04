<?php

namespace App\View\Composers;

use App\Services\Portal\PortalMovieServerCatalog;
use App\Support\ResellerBranding;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class PortalViewComposer
{
    public function compose(View $view): void
    {
        $customer = Auth::guard('customer')->user();

        $data = ResellerBranding::forCustomer($customer);

        if ($customer !== null) {
            $data['movieServers'] = PortalMovieServerCatalog::forPortal((int) $customer->tenant_id);
        }

        $view->with($data);
    }
}
