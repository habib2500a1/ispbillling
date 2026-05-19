<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PortalAccountController extends Controller
{
    public function editPassword(): View
    {
        return view('portal.account-password', [
            'customer' => auth('customer')->user(),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($validated['current_password'], (string) $customer->portal_password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $customer->forceFill([
            'portal_password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('portal.profile.index')
            ->with('status', 'Portal password updated successfully.');
    }
}
