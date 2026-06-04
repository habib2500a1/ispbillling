<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Support\ResellerBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ResellerBrandingController extends Controller
{
    public function edit(): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.enterprise.branding', [
            'reseller' => $reseller,
            'links' => ResellerBranding::shareableLinks($reseller),
            'sslGuide' => ResellerBranding::sslSetupGuide($reseller),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:255'],
            'brand_primary_color' => ['nullable', 'string', 'max:16'],
            'brand_secondary_color' => ['nullable', 'string', 'max:16'],
            'portal_subdomain' => ['nullable', 'string', 'max:63', 'alpha_dash'],
            'portal_custom_domain' => ['nullable', 'string', 'max:255'],
            'portal_login_message' => ['nullable', 'string', 'max:2000'],
            'white_label_enabled' => ['nullable', 'boolean'],
            'brand_logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = collect($validated)->except(['brand_logo', 'white_label_enabled'])->filter()->all();
        $data['white_label_enabled'] = $request->boolean('white_label_enabled');

        if ($request->hasFile('brand_logo')) {
            $path = $request->file('brand_logo')->store('reseller-logos', 'public');
            if (filled($reseller->brand_logo_path)) {
                Storage::disk('public')->delete($reseller->brand_logo_path);
            }
            $data['brand_logo_path'] = $path;
        }

        $reseller->update($data);

        return back()->with('status', 'Branding settings saved.');
    }
}
