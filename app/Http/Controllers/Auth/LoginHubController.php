<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\CompanyBranding;
use Illuminate\View\View;

class LoginHubController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.login-hub', [
            'companyName' => CompanyBranding::name(),
            'logo' => CompanyBranding::logoUrl(),
            'portalEnabled' => (bool) config('portal.enabled', true),
            'resellerEnabled' => (bool) config('reseller_portal.enabled', true),
            'customerLoginUrl' => route('portal.login'),
            'adminLoginUrl' => route('filament.admin.auth.login'),
            'resellerLoginUrl' => route('reseller.login'),
            'payUrl' => url('/pay'),
        ]);
    }
}
