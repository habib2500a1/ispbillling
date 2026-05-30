@extends('reseller.layout')

@section('title', 'Branding')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="text-sm font-semibold text-sky-700">← Settings</a>
    </div>

    <div class="rsl-card p-6 max-w-xl">
        <h1 class="text-xl font-bold text-slate-900">White-label branding</h1>
        <p class="mt-1 text-sm text-slate-600">
            Shown on /pay, customer portal, invoices and receipts for your subscribers.
            Logo, name and color are set by your ISP admin.
        </p>

        <form method="post" action="{{ route('reseller.settings.branding.update') }}" class="mt-6 grid gap-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Tagline</label>
                <input name="company_tagline" value="{{ old('company_tagline', $state['company_tagline']) }}" maxlength="255" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" placeholder="Fast internet for your area">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Address</label>
                <textarea name="company_address" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" placeholder="Shop 12, Main Road, Dhaka">{{ old('company_address', $state['company_address']) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Invoice / receipt footer</label>
                <textarea name="invoice_footer" rows="3" maxlength="1000" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" placeholder="Thank you. For billing help call {{ $reseller->phone ?: 'our office' }}.">{{ old('invoice_footer', $state['invoice_footer']) }}</textarea>
            </div>

            <button type="submit" class="rsl-btn w-full">Save branding</button>
        </form>
    </div>

    <div class="rsl-card mt-6 p-6 max-w-xl text-sm text-slate-600">
        <p class="font-semibold text-slate-800">Share with customers</p>
        <ul class="mt-2 space-y-2 font-mono text-xs break-all">
            <li><span class="font-sans font-semibold text-slate-700">Bill pay:</span> {{ $shareLinks['pay'] }}</li>
            <li><span class="font-sans font-semibold text-slate-700">Portal login:</span> {{ $shareLinks['portal_login'] }}</li>
            @if (! empty($shareLinks['subdomain_pay']))
                <li><span class="font-sans font-semibold text-slate-700">Subdomain pay:</span> {{ $shareLinks['subdomain_pay'] }}</li>
                <li><span class="font-sans font-semibold text-slate-700">Subdomain portal:</span> {{ $shareLinks['subdomain_portal'] }}</li>
            @endif
        </ul>
    </div>

    <div class="rsl-card mt-6 p-6 max-w-xl text-sm text-slate-600">
        <p class="font-semibold text-slate-800">Subdomain &amp; SSL (optional)</p>
        <pre class="mt-2 whitespace-pre-wrap rounded-lg bg-slate-100 p-3 text-xs leading-relaxed text-slate-700">{{ $sslGuide }}</pre>
    </div>
@endsection
