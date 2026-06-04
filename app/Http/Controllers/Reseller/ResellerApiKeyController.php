<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerApiKey;
use App\Services\Resellers\ResellerApiKeyService;
use App\Support\ResellerPortalPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResellerApiKeyController extends Controller
{
    public function index(): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.enterprise.api-keys', [
            'reseller' => $reseller,
            'apiKeyPermissionOptions' => ResellerPortalPermission::labelsForApiKeys($reseller),
            'keys' => $reseller->apiKeys()->orderByDesc('id')->get(),
            'usageLogs' => \App\Models\ResellerApiUsageLog::query()
                ->where('reseller_id', $reseller->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function store(Request $request, ResellerApiKeyService $service): RedirectResponse
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $allowed = array_values(array_intersect(
            $reseller->portalPermissions(),
            ResellerPortalPermission::assignableToApiKeys(),
        ));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in($allowed)],
        ]);

        $abilities = isset($validated['abilities'])
            ? array_values(array_intersect($validated['abilities'], $allowed))
            : null;
        if ($abilities === []) {
            $abilities = null;
        }

        $result = $service->create($reseller, $validated['name'], $abilities);

        return back()
            ->with('status', 'API key created. Copy it now — it will not be shown again.')
            ->with('new_api_key', $result['plain']);
    }

    public function destroy(ResellerApiKey $apiKey, ResellerApiKeyService $service): RedirectResponse
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless((int) $apiKey->reseller_id === (int) $reseller->id, 404);

        $service->revoke($apiKey);

        return back()->with('status', 'API key revoked.');
    }
}
