<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CompanyBranding;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fast GET /admin/login — same CSS as Filament auth, no Livewire boot.
 */
final class AdminLoginPageController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user('web') !== null) {
            return redirect()->intended('/admin');
        }

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();

        return view('admin.login', [
            'companyName' => CompanyBranding::name(),
            'companyTagline' => CompanyBranding::tagline(),
            'companyLogo' => CompanyBranding::logoUrl(),
            'favicon' => CompanyBranding::faviconUrl(),
            'filamentThemeHtml' => filament()->getTheme()->getHtml(),
            'filamentFontHtml' => filament()->getFontHtml(),
            'fontFamily' => filament()->getFontFamily(),
        ]);
    }
}
