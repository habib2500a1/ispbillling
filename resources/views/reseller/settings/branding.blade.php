@extends('reseller.layout')

@section('title', 'Branding')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="rsl-page-back">← Settings</a>
    </div>

    @include('reseller.partials.page-header', [
        'title' => 'White-label branding',
        'subtitle' => 'Pay page, customer portal, invoices — logo and colors from your ISP admin.',
    ])

    @if ($reseller->white_label_enabled)
        <div class="rsl-callout rsl-callout--info mb-4" style="max-width:48rem">
            <p class="font-semibold">White-label is active</p>
            <p class="mt-1 text-sm">Customers see your partner name{{ $reseller->logoUrl() ? ', logo,' : '' }} and custom footer on bills, money receipts, and the public pay page.</p>
            @if ($rslLogo = ($reseller->logoUrl() ?: null))
                <img src="{{ $rslLogo }}" alt="" class="mt-3 max-h-12 rounded-lg border border-slate-200 bg-white p-2">
            @endif
        </div>
    @else
        <div class="rsl-callout mb-4" style="max-width:48rem">
            <p class="font-semibold">HQ branding</p>
            <p class="mt-1 text-sm">White-label is off — documents use your ISP company name. Ask HQ to enable white-label on your partner account.</p>
        </div>
    @endif

    <div class="rsl-panel rsl-panel-pad" style="max-width:32rem">
        <form method="post" action="{{ route('reseller.settings.branding.update') }}" class="rsl-form-grid">
            @csrf
            @method('PUT')

            <div class="rsl-field">
                <label class="rsl-field-label">Tagline</label>
                <input name="company_tagline" value="{{ old('company_tagline', $state['company_tagline']) }}" maxlength="255" class="rsl-input" placeholder="Fast internet for your area">
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label">Address</label>
                <textarea name="company_address" rows="2" maxlength="500" class="rsl-input" placeholder="Shop 12, Main Road, Dhaka">{{ old('company_address', $state['company_address']) }}</textarea>
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label">Invoice / receipt footer</label>
                <textarea name="invoice_footer" rows="3" maxlength="1000" class="rsl-input" placeholder="Thank you. For billing help call {{ $reseller->phone ?: 'our office' }}.">{{ old('invoice_footer', $state['invoice_footer']) }}</textarea>
            </div>

            <button type="submit" class="rsl-btn w-full">Save branding</button>
        </form>
    </div>

    <div class="rsl-panel rsl-panel-pad mt-6" style="max-width:32rem">
        <h2 class="rsl-panel-title">Share with customers</h2>
        <ul class="mt-3 space-y-2 font-mono text-xs break-all" style="color:var(--rsl-text-muted)">
            <li><span class="font-sans font-semibold" style="color:var(--rsl-text)">Bill pay:</span> {{ $shareLinks['pay'] }}</li>
            <li><span class="font-sans font-semibold" style="color:var(--rsl-text)">Portal login:</span> {{ $shareLinks['portal_login'] }}</li>
            @if (! empty($shareLinks['subdomain_pay']))
                <li><span class="font-sans font-semibold" style="color:var(--rsl-text)">Subdomain pay:</span> {{ $shareLinks['subdomain_pay'] }}</li>
                <li><span class="font-sans font-semibold" style="color:var(--rsl-text)">Subdomain portal:</span> {{ $shareLinks['subdomain_portal'] }}</li>
            @endif
        </ul>
    </div>

    <div class="rsl-panel rsl-panel-pad mt-6" style="max-width:32rem">
        <h2 class="rsl-panel-title">Subdomain &amp; SSL (optional)</h2>
        <pre class="mt-3 whitespace-pre-wrap rounded-lg p-3 text-xs leading-relaxed rsl-code-block">{{ $sslGuide }}</pre>
    </div>
@endsection
