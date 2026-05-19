<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalLoginRequest;
use App\Http\Requests\Portal\PortalOtpVerifyRequest;
use App\Models\Customer;
use App\Services\Portal\PortalOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class PortalLoginController extends Controller
{
    public function create(Request $request): View|Response
    {
        if ($request->query('abandon') === '1') {
            $request->session()->forget(['portal_otp_customer_id', 'portal_otp_remember']);
        }

        if ($request->session()->has('portal_session_expired')) {
            $request->session()->regenerateToken();
        }

        return response()
            ->view('portal.login', [
                'portalOtpEnabled' => (bool) config('portal.otp.enabled', false),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function store(PortalLoginRequest $request, PortalOtpService $otp): RedirectResponse
    {
        $login = trim((string) $request->validated('login'));
        $password = (string) $request->validated('password');
        $remember = (bool) $request->boolean('remember');

        $customer = Customer::findForPortalLogin($login);

        if (! $customer || ! Hash::check($password, (string) $customer->portal_password)) {
            return back()->withErrors([
                'login' => __('These credentials do not match our records.'),
            ])->onlyInput('login');
        }

        if ($otp->isEnabled()) {
            $logOnly = (bool) config('portal.otp.log_delivery_only', false);
            $email = $customer->email;
            $emailOk = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            if (! $logOnly && ! $emailOk) {
                return back()->withErrors([
                    'login' => __('Two-step login is enabled, but your account has no valid email for the code. Please contact your provider.'),
                ])->onlyInput('login');
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

                return back()->withErrors([
                    'login' => __('We could not send your login code. Please try again or contact your provider.'),
                ])->onlyInput('login');
            }

            return redirect()->route('portal.login.otp');
        }

        Auth::guard('customer')->login($customer, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function otpForm(Request $request): View|RedirectResponse
    {
        if (! (bool) config('portal.otp.enabled', false)) {
            return redirect()->route('portal.login');
        }

        $customerId = $request->session()->get('portal_otp_customer_id');
        $id = filter_var($customerId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return redirect()->route('portal.login')->withErrors([
                'login' => __('Your login session expired. Please sign in again.'),
            ]);
        }

        return view('portal.login-otp');
    }

    public function otpVerify(PortalOtpVerifyRequest $request, PortalOtpService $otp): RedirectResponse
    {
        if (! (bool) config('portal.otp.enabled', false)) {
            return redirect()->route('portal.login');
        }

        $customerId = $request->session()->get('portal_otp_customer_id');
        $remember = (bool) $request->session()->get('portal_otp_remember', false);
        $id = filter_var($customerId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return redirect()->route('portal.login')->withErrors([
                'login' => __('Your login session expired. Please sign in again.'),
            ]);
        }

        $code = (string) $request->validated('code');
        if (! $otp->verify($id, $code)) {
            return back()->withErrors([
                'code' => __('That code is incorrect or has expired.'),
            ])->onlyInput('code');
        }

        $customer = Customer::query()->withoutGlobalScopes()->whereKey($id)->first();
        if (! $customer instanceof Customer) {
            $otp->forget($id);

            return redirect()->route('portal.login')->withErrors([
                'login' => __('Your account could not be found. Please contact your provider.'),
            ]);
        }

        $request->session()->forget(['portal_otp_customer_id', 'portal_otp_remember']);

        Auth::guard('customer')->login($customer, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
