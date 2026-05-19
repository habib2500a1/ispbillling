<?php

namespace App\Http\Controllers;

use App\Services\Hotspot\HotspotVoucherRedeemer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HotspotPortalController extends Controller
{
    public function index(): View
    {
        abort_unless(config('hotspot.enabled', true), 404);

        return view('hotspot.index', [
            'welcome' => config('hotspot.welcome_message'),
        ]);
    }

    public function redeem(Request $request, HotspotVoucherRedeemer $redeemer): RedirectResponse
    {
        abort_unless(config('hotspot.enabled', true), 404);

        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $result = $redeemer->redeem(
            $request->string('code')->toString(),
            $request->header('X-Client-Mac'),
        );

        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['message']])->withInput();
        }

        return back()->with('hotspot_success', $result);
    }
}
