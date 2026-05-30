<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Reseller\ResellerBrandingSettings;
use App\Services\Reseller\ResellerIntegrationSettings;
use App\Support\ResellerBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerSettingsController extends Controller
{
    public function index(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless($this->canAccessSettings($reseller), 403);

        return view('reseller.settings.index', [
            'reseller' => $reseller,
            'summary' => ResellerIntegrationSettings::summary($reseller),
            'canIntegrations' => ResellerIntegrationSettings::canManage($reseller),
            'canBranding' => ResellerBrandingSettings::canManage($reseller),
            'shareLinks' => ResellerBrandingSettings::canManage($reseller)
                ? ResellerBranding::shareableLinks($reseller)
                : null,
        ]);
    }

    public function branding(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerBrandingSettings::canManage($reseller), 403);

        return view('reseller.settings.branding', [
            'reseller' => $reseller,
            'state' => ResellerBrandingSettings::formState($reseller),
            'shareLinks' => ResellerBranding::shareableLinks($reseller),
            'sslGuide' => ResellerBranding::sslSetupGuide($reseller),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerBrandingSettings::canManage($reseller), 403);

        $validated = $request->validate([
            'company_tagline' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        ResellerBrandingSettings::save($reseller, $validated);

        return redirect()
            ->route('reseller.settings.branding')
            ->with('status', 'Branding settings saved.');
    }

    private function canAccessSettings(\App\Models\Reseller $reseller): bool
    {
        return ResellerIntegrationSettings::canManage($reseller)
            || ResellerBrandingSettings::canManage($reseller);
    }

    public function sms(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerIntegrationSettings::canManage($reseller), 403);

        return view('reseller.settings.sms', [
            'reseller' => $reseller,
            'state' => ResellerIntegrationSettings::smsFormState($reseller),
        ]);
    }

    public function updateSms(Request $request): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerIntegrationSettings::canManage($reseller), 403);

        $validated = $request->validate([
            'sms_enabled' => ['nullable', 'boolean'],
            'sms_provider' => ['required', 'in:khudebarta,bulksmsbd,sslwireless,custom'],
            'sms_api_url' => ['nullable', 'string', 'max:500'],
            'sms_sender_id' => ['nullable', 'string', 'max:32'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'sms_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        ResellerIntegrationSettings::saveSms($reseller, $validated);

        return redirect()
            ->route('reseller.settings.sms')
            ->with('status', 'SMS settings saved.');
    }

    public function payment(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerIntegrationSettings::canManage($reseller), 403);

        return view('reseller.settings.payment', [
            'reseller' => $reseller,
            'state' => ResellerIntegrationSettings::paymentFormState($reseller),
            'ingestUrl' => url('/api/v1/mfs/sms/ingest'),
        ]);
    }

    public function updatePayment(Request $request): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless(ResellerIntegrationSettings::canManage($reseller), 403);

        $validated = $request->validate([
            'bkash_enabled' => ['nullable', 'boolean'],
            'bkash_personal_number' => ['nullable', 'string', 'max:20'],
            'bkash_personal_name' => ['nullable', 'string', 'max:120'],
            'nagad_enabled' => ['nullable', 'boolean'],
            'nagad_personal_number' => ['nullable', 'string', 'max:20'],
            'mfs_ingest_enabled' => ['nullable', 'boolean'],
            'mfs_device_key' => ['nullable', 'string', 'max:128'],
        ]);

        ResellerIntegrationSettings::savePayment($reseller, $validated);

        return redirect()
            ->route('reseller.settings.payment')
            ->with('status', 'Payment settings saved.');
    }
}
