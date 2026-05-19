<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\PortalSignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalSignupController extends Controller
{
    public function create(PortalSignupService $signup): View
    {
        abort_unless(config('portal.signup.enabled', true), 404);

        return view('portal.signup', [
            'areas' => $signup->areaOptions(),
            'packages' => $signup->packageOptions(),
        ]);
    }

    public function store(Request $request, PortalSignupService $signup): RedirectResponse
    {
        abort_unless(config('portal.signup.enabled', true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'package_id' => [
                'nullable',
                'integer',
                Rule::exists('packages', 'id')->where(fn ($q) => $q->where('is_active', true)->where('show_on_website', true)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $signup->submit($validated);

        return redirect()
            ->route('portal.signup.success')
            ->with('status', 'Your connection request was received. Our team will contact you shortly.');
    }

    public function success(): View
    {
        abort_unless(config('portal.signup.enabled', true), 404);

        return view('portal.signup-success');
    }
}
