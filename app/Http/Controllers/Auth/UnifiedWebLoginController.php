<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Models\User;
use App\Services\Portal\PortalOtpService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Services\Resellers\ResellerPortalDeviceTracker;
use App\Services\Resellers\ResellerPortalLoginLogger;
use App\Services\Staff\ActivityLogger;
use App\Services\Staff\StaffLoginService;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side unified login — no JavaScript required.
 */
final class UnifiedWebLoginController extends Controller
{
    public function __invoke(
        Request $request,
        StaffLoginService $staffLogin,
        PortalOtpService $otp,
        ResellerPortalDeviceTracker $devices,
        ResellerPortalLoginLogger $loginLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'in:0,1,true,false,on,off,yes,no'],
        ]);

        $login = trim($validated['login']);
        $password = $validated['password'];
        $remember = filter_var($validated['remember'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach ($this->attemptOrder($login) as $role) {
            $redirect = match ($role) {
                'staff' => $this->attemptStaff($request, $staffLogin, $login, $password, $remember),
                'customer' => $this->attemptCustomer($request, $otp, $login, $password, $remember),
                'reseller' => $this->attemptReseller(
                    $request,
                    $devices,
                    $loginLogger,
                    $login,
                    $password,
                    $remember,
                ),
                default => null,
            };

            if ($redirect !== null) {
                return $redirect;
            }
        }

        return redirect()
            ->route('login.hub')
            ->withInput($request->only('login', 'remember'))
            ->withErrors(['login' => __('These credentials do not match our records.')]);
    }

    /**
     * @return list<string>
     */
    private function attemptOrder(string $login): array
    {
        if (preg_match('/^rsl[-_]/i', $login)) {
            return ['reseller', 'customer', 'staff'];
        }

        if (preg_match('/^(cust|demo)[-_]/i', $login)) {
            return ['customer', 'reseller', 'staff'];
        }

        $digits = preg_replace('/\D/', '', $login);
        if (preg_match('/^01[0-9]{9}$/', preg_replace('/[\s\-+]/', '', $login)) || preg_match('/^88/', $digits)) {
            return ['customer', 'reseller', 'staff'];
        }

        if (str_contains($login, '@') || strtolower($login) === 'admin') {
            return ['staff', 'customer', 'reseller'];
        }

        if (strlen($digits) >= 6) {
            return ['customer', 'reseller', 'staff'];
        }

        return ['customer', 'staff', 'reseller'];
    }

    private function attemptStaff(
        Request $request,
        StaffLoginService $staffLogin,
        string $login,
        string $password,
        bool $remember,
    ): ?RedirectResponse {
        if (! str_contains($login, '@') && strtolower($login) !== 'admin') {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where(fn ($q) => $q->where('email', $login)->orWhere('name', $login))
                ->first();
            if ($user === null) {
                return null;
            }
        }

        $result = $staffLogin->attempt($login, $password, $remember, $request->ip());

        if ($result['ok']) {
            app(ActivityLogger::class)->log('login', 'Staff signed in', auth('web')->user());
            $request->session()->save();

            return redirect()->to('/admin', status: 303);
        }

        $email = $staffLogin->resolveCredentials($login, $password)['email'];
        if (User::query()->withoutGlobalScopes()->where('email', $email)->exists()) {
            app(ActivityLogger::class)->log(
                'login.failed',
                'Failed staff login attempt',
                null,
                ['login' => $login],
            );

            return redirect()
                ->route('login.hub')
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => $result['error']]);
        }

        return null;
    }

    private function attemptCustomer(
        Request $request,
        PortalOtpService $otp,
        string $login,
        string $password,
        bool $remember,
    ): ?RedirectResponse {
        if (! config('portal.enabled', true)) {
            return null;
        }

        $customer = Customer::findForPortalLogin($login);
        if ($customer === null || ! Hash::check($password, (string) $customer->portal_password)) {
            return null;
        }

        if ($otp->isEnabled()) {
            $logOnly = (bool) config('portal.otp.log_delivery_only', false);
            $email = $customer->email;
            $emailOk = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            if (! $logOnly && ! $emailOk) {
                return redirect()
                    ->route('login.hub')
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors([
                        'login' => __('Two-step login is enabled, but your account has no valid email for the code. Please contact your provider.'),
                    ]);
            }

            $request->session()->regenerate();
            $request->session()->put('portal_otp_customer_id', $customer->id);
            $request->session()->put('portal_otp_remember', $remember);

            try {
                $otp->startChallenge($customer);
            } catch (Throwable $e) {
                Log::channel('single')->error('portal.otp_start_failed', [
                    'customer_id' => $customer->id,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
                $request->session()->forget(['portal_otp_customer_id', 'portal_otp_remember']);

                return redirect()
                    ->route('login.hub')
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors([
                        'login' => __('We could not send your login code. Please try again or contact your provider.'),
                    ]);
            }

            return redirect()->route('portal.login.otp');
        }

        Auth::guard('customer')->login($customer, $remember);
        $customer->recordPortalLogin();
        $request->session()->regenerate();
        $request->session()->save();

        return redirect()->intended(route('portal.dashboard'));
    }

    private function attemptReseller(
        Request $request,
        ResellerPortalDeviceTracker $devices,
        ResellerPortalLoginLogger $loginLogger,
        string $login,
        string $password,
        bool $remember,
    ): ?RedirectResponse {
        if (! config('reseller_portal.enabled', true)) {
            return null;
        }

        $portalSession = app(ResellerPortalSession::class);

        $staff = ResellerStaff::findForPortalLogin($login);
        if ($staff !== null && Hash::check($password, (string) $staff->password)) {
            $reseller = $staff->reseller;
            if ($reseller === null || ! $reseller->is_active || ! $reseller->hasPortalAccess()) {
                return null;
            }

            if (! $loginLogger->isIpAllowed($reseller, $request->ip())) {
                return redirect()
                    ->route('login.hub')
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors(['login' => __('Access denied from this IP address.')]);
            }

            $request->session()->regenerate();
            Auth::guard('reseller')->login($reseller, $remember);
            $portalSession->bindStaff($staff);
            $staff->recordLogin();
            app(\App\Services\Resellers\ResellerPortalAccessService::class)->bypassTwoFactorForSession($request);
            $devices->recordLogin($reseller, $request);
            $loginLogger->logAttempt($reseller, $request, true, $login, $staff);
            app(ResellerPortalActivityLogger::class)->log($reseller, 'portal.login.staff', $staff, ['login' => $staff->login], $request);
            $request->session()->save();

            return redirect()->intended(route('reseller.dashboard'));
        }

        $reseller = Reseller::findForPortalLogin($login);
        if ($reseller === null || ! Hash::check($password, (string) $reseller->portal_password)) {
            return null;
        }

        if (! $loginLogger->isIpAllowed($reseller, $request->ip())) {
            return redirect()
                ->route('login.hub')
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => __('Access denied from this IP address.')]);
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
        $request->session()->save();

        return redirect()->intended(route('reseller.dashboard'));
    }
}
