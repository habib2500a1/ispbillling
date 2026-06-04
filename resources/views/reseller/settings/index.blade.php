@extends('reseller.layout')

@section('title', 'Settings')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-xl font-bold text-slate-900">Settings</h1>
        <p class="mt-1 text-sm text-slate-600">Integrations and customer-facing branding for your business.</p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @if ($canBranding ?? false)
            <a href="{{ route('reseller.settings.branding') }}" class="rsl-card block p-6 transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">White-label branding</h2>
                        <p class="mt-1 text-sm text-slate-600">Tagline, address, invoice footer, customer links.</p>
                    </div>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">Active</span>
                </div>
            </a>
        @endif

        @if ($canIntegrations ?? false)
            <a href="{{ route('reseller.settings.sms') }}" class="rsl-card block p-6 transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">SMS gateway</h2>
                        <p class="mt-1 text-sm text-slate-600">KhudeBarta, BulkSMSBD, sender ID for your customers.</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ ($summary['sms_active'] ?? false) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ ($summary['sms_active'] ?? false) ? 'Active' : 'Not set' }}
                    </span>
                </div>
            </a>

            <a href="{{ route('reseller.settings.payment') }}" class="rsl-card block p-6 transition hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">Personal bKash / Nagad</h2>
                        <p class="mt-1 text-sm text-slate-600">Send Money numbers and SMS auto-verify.</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ (($summary['bkash_active'] ?? false) || ($summary['nagad_active'] ?? false)) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ (($summary['bkash_active'] ?? false) || ($summary['nagad_active'] ?? false)) ? 'Active' : 'Not set' }}
                    </span>
                </div>
            </a>
        @endif
    </div>

    @if ($canIntegrations ?? false)
        <div class="rsl-card mt-6 p-6 text-sm text-slate-600">
            <p class="font-semibold text-slate-800">Integrations</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>SMS uses your API keys and sender name for your subscribers.</li>
                <li>Personal MFS numbers appear on customer payment pages.</li>
                <li>MFS SMS forwarder auto-verifies TrxID with your device key.</li>
            </ul>
        </div>
    @endif
@endsection
