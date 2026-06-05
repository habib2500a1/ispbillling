<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResellerCustomerMediaController extends Controller
{
    public function storeInstallationPhoto(Request $request, Customer $customer, ResellerCustomerService $customers): RedirectResponse
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            abort(403);
        }

        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        $request->validate([
            'installation_photo' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('installation_photo')->store(
            'reseller-installations/'.$reseller->tenant_id.'/'.$customer->id,
            'public',
        );

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $old = $meta['installation_photo_path'] ?? null;
        if (filled($old) && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }
        $meta['installation_photo_path'] = $path;
        $customer->meta = $meta;
        $customer->save();

        return redirect()
            ->route('reseller.customers.show', $customer)
            ->with('status', 'Installation photo saved.');
    }
}
