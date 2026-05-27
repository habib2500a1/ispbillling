<?php

namespace App\View\Composers;

use App\Services\Portal\PortalMovieServerCatalog;
use App\Support\CompanyBranding;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class PortalViewComposer
{
    public function compose(View $view): void
    {
        $data = [
            'companyName' => CompanyBranding::name(),
            'companyTagline' => CompanyBranding::tagline(),
            'companyLogo' => CompanyBranding::logoUrl(),
            'companyPhone' => CompanyBranding::phone(),
        ];

        $customer = Auth::guard('customer')->user();
        if ($customer !== null) {
            $data['movieServers'] = PortalMovieServerCatalog::forPortal((int) $customer->tenant_id);
        }

        $view->with($data);
    }
}
