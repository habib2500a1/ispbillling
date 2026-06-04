@extends('reseller.layout')

@section('title', 'Settings')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Settings',
        'subtitle' => 'Integrations and customer-facing branding.',
    ])

    <div class="rsl-settings-grid">
        @if ($canBranding ?? false)
            <a href="{{ route('reseller.settings.branding') }}" class="rsl-settings-tile">
                <div class="flex justify-between gap-2" style="display:flex;justify-content:space-between">
                    <div>
                        <h2>White-label (limited)</h2>
                        <p>Tagline, address, invoice footer</p>
                    </div>
                    <span class="rsl-badge-pill rsl-badge-pill--ok">Active</span>
                </div>
            </a>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('reseller.branding.edit'))
            <a href="{{ route('reseller.branding.edit') }}" class="rsl-settings-tile">
                <h2>Full branding</h2>
                <p>Logo, colors, custom domain</p>
            </a>
        @endif

        @if ($canIntegrations ?? false)
            <a href="{{ route('reseller.settings.sms') }}" class="rsl-settings-tile">
                <div style="display:flex;justify-content:space-between;gap:0.5rem">
                    <div>
                        <h2>SMS gateway</h2>
                        <p>KhudeBarta, BulkSMSBD, sender ID</p>
                    </div>
                    <span class="rsl-badge-pill {{ ($summary['sms_active'] ?? false) ? 'rsl-badge-pill--ok' : 'rsl-badge-pill--muted' }}">
                        {{ ($summary['sms_active'] ?? false) ? 'Active' : 'Not set' }}
                    </span>
                </div>
            </a>
            <a href="{{ route('reseller.settings.payment') }}" class="rsl-settings-tile">
                <div style="display:flex;justify-content:space-between;gap:0.5rem">
                    <div>
                        <h2>Personal bKash / Nagad</h2>
                        <p>With SMS auto-verify</p>
                    </div>
                    <span class="rsl-badge-pill {{ (($summary['bkash_active'] ?? false) || ($summary['nagad_active'] ?? false)) ? 'rsl-badge-pill--ok' : 'rsl-badge-pill--muted' }}">
                        {{ (($summary['bkash_active'] ?? false) || ($summary['nagad_active'] ?? false)) ? 'Active' : 'Not set' }}
                    </span>
                </div>
            </a>
        @endif
    </div>

    @if ($canIntegrations ?? false)
        <div class="rsl-panel rsl-panel-pad mt-4 text-sm" style="color:var(--rsl-text-muted)">
            <p class="rsl-panel-title">Notes</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>SMS — message subscribers with your API key</li>
                <li>MFS — your number on the customer pay page</li>
                <li>SMS forwarder — TrxID auto-verify</li>
            </ul>
        </div>
    @endif
@endsection
