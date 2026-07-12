<?php

namespace App\Http\Controllers;

use App\Models\CustomersInfo;
use App\Services\Portal\CustomerPortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPortalController extends Controller
{
    /** anetbd-style staff portal login by numeric customer id */
    public function loginById(int $customer, Request $request): RedirectResponse
    {
        $this->authorizeStaff();

        $record = CustomersInfo::with('pppUser')->findOrFail($customer);

        return $this->loginPppUser($record, $request);
    }

    public function login(string $id, Request $request): RedirectResponse
    {
        $this->authorizeStaff();

        return $this->loginPppUser($this->resolveCustomer($id), $request);
    }

    public function accessToken(string $token, Request $request, CustomerPortalAccessService $portal): RedirectResponse
    {
        $ppp = $portal->findPppUserByAccessToken($token);

        if (! $ppp) {
            return redirect()
                ->route('filament.portal.auth.login')
                ->withErrors(['data.username' => __('Invalid or expired portal access link.')]);
        }

        Auth::shouldUse('ppp');
        Auth::guard('ppp')->login($ppp->fresh(), false);
        $request->session()->regenerate();
        session(['portal_login_time' => now()]);

        return redirect()->route('filament.portal.pages.portal-dashboard');
    }

    public function regenerateToken(string $id, CustomerPortalAccessService $portal): RedirectResponse
    {
        $this->authorizeStaff();

        $customer = $this->resolveCustomer($id);

        try {
            $plain = $portal->regenerateAccessToken($customer);
            $url = route('portal.access.token', ['token' => $plain]);
            flash()->success(__('Portal token regenerated. Copy the link from customer view.'));
            session(['portal_token_preview_'.$customer->customer_unique_id => $url]);
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }

        return redirect()->route('customers.show', $id);
    }

    protected function loginPppUser(CustomersInfo $customer, Request $request): RedirectResponse
    {
        $ppp = $customer->pppUser;

        if (! $ppp) {
            flash()->error(__('Customer has no PPP user — portal login is not available.'));

            return redirect()->route('customers.show', encrypt($customer->customer_unique_id));
        }

        $adminId = Auth::guard('web')->id();

        Auth::shouldUse('ppp');
        Auth::guard('ppp')->login($ppp->fresh(), false);
        $request->session()->regenerate();
        session([
            'portal_login_time' => now(),
            'portal_impersonated_by_admin' => $adminId,
        ]);

        return redirect()->route('filament.portal.pages.portal-dashboard');
    }

    protected function authorizeStaff(): void
    {
        if (! hasAccess(['Super Admin'], ['all-customer', 'edit-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    protected function resolveCustomer(string $id): CustomersInfo
    {
        try {
            $uniqueId = decrypt($id);
        } catch (\Throwable) {
            abort(404);
        }

        return CustomersInfo::with('pppUser')
            ->where('customer_unique_id', $uniqueId)
            ->firstOrFail();
    }
}
