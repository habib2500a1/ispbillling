<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Resellers\ResellerTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerTwoFactorController extends Controller
{
    public function setup(ResellerTwoFactorService $twoFactor): View
    {
        $reseller = auth('reseller')->user();
        $secret = session('reseller.2fa_setup_secret') ?? $twoFactor->generateSecret();
        session(['reseller.2fa_setup_secret' => $secret]);

        return view('reseller.two-factor-setup', [
            'reseller' => $reseller,
            'secret' => $secret,
            'qrUrl' => $twoFactor->getQrCodeUrl($reseller, $secret),
        ]);
    }

    public function confirmSetup(Request $request, ResellerTwoFactorService $twoFactor): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $secret = (string) session('reseller.2fa_setup_secret');
        $codes = $twoFactor->enable($reseller, $secret, (string) $request->input('code'));

        if ($codes === false) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        session()->forget('reseller.2fa_setup_secret');
        session(['reseller.2fa_recovery_codes' => $codes]);

        return redirect()->route('reseller.dashboard')->with('status', 'Two-factor authentication enabled.');
    }

    public function challenge(): View
    {
        return view('reseller.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, ResellerTwoFactorService $twoFactor): RedirectResponse
    {
        $reseller = auth('reseller')->user();

        if (! $twoFactor->verify($reseller, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid code.']);
        }

        $request->session()->put('reseller.2fa_passed', true);

        return redirect()->intended(route('reseller.dashboard'));
    }
}
