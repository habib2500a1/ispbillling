<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reseller\ResellerLoginRequest;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ResellerLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        if (Auth::guard('reseller')->check()) {
            return redirect()->route('reseller.dashboard');
        }

        return view('reseller.login');
    }

    public function store(ResellerLoginRequest $request): RedirectResponse
    {
        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        $login = trim((string) $request->validated('login'));
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');

        $reseller = Reseller::findForPortalLogin($login);

        if (! $reseller || ! Hash::check($password, (string) $reseller->portal_password)) {
            return back()->withErrors([
                'login' => __('These credentials do not match our records.'),
            ])->onlyInput('login');
        }

        $request->session()->regenerate();
        Auth::guard('reseller')->login($reseller, $remember);
        $reseller->forceFill(['portal_last_login_at' => now()])->save();

        return redirect()->intended(route('reseller.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('reseller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('reseller.login');
    }
}
