<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\CompanyBranding;
use Illuminate\Http\Response;

class LoginHubController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('auth.login-hub', [
                'companyName' => CompanyBranding::name(),
                'logo' => CompanyBranding::logoUrl(),
                'portalEnabled' => (bool) config('portal.enabled', true),
                'payUrl' => url('/pay'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
