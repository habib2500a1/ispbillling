<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CompanyBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One-hop after POST login so browsers commit session cookies before /admin Livewire boot.
 */
final class AdminLoginCompleteController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user('web') === null) {
            return redirect()->route('login.hub')
                ->withErrors(['login' => __('Your session could not be started. Please try again.')]);
        }

        return view('admin.login-complete', [
            'target' => url('/admin'),
            'companyName' => CompanyBranding::name(),
            'logo' => CompanyBranding::logoUrl(),
        ]);
    }
}
