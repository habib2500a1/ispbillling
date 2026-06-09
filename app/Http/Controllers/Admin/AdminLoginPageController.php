<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CompanyBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fast GET /admin/login — unified login hub UI, no Livewire boot.
 */
final class AdminLoginPageController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user('web') !== null) {
            return redirect()->intended('/admin');
        }

        return response()
            ->view('admin.login', [
                'companyName' => CompanyBranding::name(),
                'companyLogo' => CompanyBranding::logoUrl(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
