<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalProfileController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer')->load(['package', 'area', 'zone']);

        return view('portal.profile.index', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $customer->forceFill([
            'email' => $validated['email'] ?? $customer->email,
            'phone' => $validated['phone'] ?? $customer->phone,
        ])->save();

        return redirect()
            ->route('portal.profile.index')
            ->with('status', 'Profile updated successfully.');
    }
}
