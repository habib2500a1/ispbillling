<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reseller\ResellerLoginRequest;
use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Services\Resellers\ResellerPortalAccessService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Services\Resellers\ResellerPortalDeviceTracker;
use App\Services\Resellers\ResellerPortalLoginLogger;
use App\Services\Resellers\ResellerTwoFactorService;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ResellerLoginController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        if (Auth::guard('reseller')->check()) {
            return redirect()->route('reseller.dashboard');
        }

        return response()
            ->view('reseller.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function store(
        ResellerLoginRequest $request,
        ResellerTwoFactorService $twoFactor,
        ResellerPortalDeviceTracker $devices,
        ResellerPortalLoginLogger $loginLogger,
    ): RedirectResponse {
        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        $login = trim((string) $request->validated('login'));
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');
        $portalSession = app(ResellerPortalSession::class);

        $staff = ResellerStaff::findForPortalLogin($login);
        if ($staff !== null && Hash::check($password, (string) $staff->password)) {
            $reseller = $staff->reseller;
            if ($reseller === null || ! $reseller->is_active || ! $reseller->hasPortalAccess()) {
                $loginLogger->logAttempt($reseller, $request, false, $login, $staff, 'inactive_or_no_access');

                return back()->withErrors([
                    'login' => __('These credentials do not match our records.'),
                ])->onlyInput('login');
            }

            if (! $loginLogger->isIpAllowed($reseller, $request->ip())) {
                $loginLogger->logAttempt($reseller, $request, false, $login, $staff, 'ip_not_allowed');

                return back()->withErrors([
                    'login' => __('Access denied from this IP address.'),
                ])->onlyInput('login');
            }

            $request->session()->regenerate();
            Auth::guard('reseller')->login($reseller, $remember);
            $portalSession->bindStaff($staff);
            $staff->recordLogin();
            app(ResellerPortalAccessService::class)->bypassTwoFactorForSession($request);
            $devices->recordLogin($reseller, $request);
            $loginLogger->logAttempt($reseller, $request, true, $login, $staff);
            app(ResellerPortalActivityLogger::class)->log($reseller, 'portal.login.staff', $staff, ['login' => $staff->login], $request);

            return redirect()->intended(route('reseller.dashboard'));
        }

        $reseller = Reseller::findForPortalLogin($login);

        if (! $reseller || ! Hash::check($password, (string) $reseller->portal_password)) {
            $loginLogger->logAttempt($reseller, $request, false, $login, failureReason: 'invalid_credentials');

            return back()->withErrors([
                'login' => __('These credentials do not match our records.'),
            ])->onlyInput('login');
        }

        if (! $loginLogger->isIpAllowed($reseller, $request->ip())) {
            $loginLogger->logAttempt($reseller, $request, false, $login, failureReason: 'ip_not_allowed');

            return back()->withErrors([
                'login' => __('Access denied from this IP address.'),
            ])->onlyInput('login');
        }

        $request->session()->regenerate();
        Auth::guard('reseller')->login($reseller, $remember);
        $portalSession->clearStaff();
        $devices->recordLogin($reseller, $request);

        if ($reseller->requiresTwoFactor()) {
            $request->session()->forget('reseller.2fa_passed');

            return redirect()->route('reseller.two-factor.challenge');
        }

        $loginLogger->logAttempt($reseller, $request, true, $login);
        app(ResellerPortalActivityLogger::class)->log($reseller, 'portal.login', meta: ['login' => $reseller->portalLoginId()], request: $request);

        return redirect()->intended(route('reseller.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('reseller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('reseller.login');
    }

    public function accessToken(
        string $token,
        Request $request,
        ResellerPortalAccessService $portal,
        ResellerPortalDeviceTracker $devices,
    ): RedirectResponse {
        if (! config('reseller_portal.enabled', true)) {
            abort(404);
        }

        $reseller = $portal->findResellerByAccessToken($token);

        if ($reseller === null) {
            return redirect()
                ->route('reseller.login')
                ->withErrors(['login' => __('Invalid or expired reseller access link.')]);
        }

        $portal->ensurePortalPassword($reseller);

        Auth::guard('reseller')->login($reseller, false);
        app(ResellerPortalSession::class)->clearStaff();
        $portal->recordPortalLogin($reseller);
        $portal->bypassTwoFactorForSession($request);
        $devices->recordLogin($reseller, $request);
        $request->session()->regenerate();

        return redirect()->route('reseller.dashboard');
    }
}
