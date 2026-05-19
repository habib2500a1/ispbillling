<?php

namespace App\View\Composers;

use App\Support\CompanyBranding;
use Illuminate\View\View;

final class PortalViewComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'companyName' => CompanyBranding::name(),
            'companyTagline' => CompanyBranding::tagline(),
            'companyLogo' => CompanyBranding::logoUrl(),
            'companyPhone' => CompanyBranding::phone(),
        ]);
    }
}
