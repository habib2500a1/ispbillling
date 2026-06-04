@extends('reseller.layout')

@section('title', 'Full branding')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="rsl-page-back">← Settings</a>
    </div>

    @include('reseller.partials.page-header', [
        'title' => 'Full white-label',
        'subtitle' => 'Logo, colors, subdomain, and custom domain.',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:36rem">
        <form method="post" action="{{ route('reseller.branding.update') }}" enctype="multipart/form-data" class="rsl-form-grid">
            @csrf @method('PUT')
            <div class="rsl-field">
                <label class="rsl-field-label" for="brand_name">Brand name</label>
                <input id="brand_name" type="text" name="brand_name" value="{{ old('brand_name', $reseller->brand_name) }}" class="rsl-input">
            </div>
            <div class="rsl-form-grid rsl-form-grid--2">
                <div class="rsl-field">
                    <label class="rsl-field-label" for="brand_primary_color">Primary color</label>
                    <input id="brand_primary_color" type="text" name="brand_primary_color" value="{{ old('brand_primary_color', $reseller->brand_primary_color) }}" class="rsl-input" placeholder="#0d9488">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="brand_secondary_color">Secondary</label>
                    <input id="brand_secondary_color" type="text" name="brand_secondary_color" value="{{ old('brand_secondary_color', $reseller->brand_secondary_color) }}" class="rsl-input">
                </div>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="portal_subdomain">Portal subdomain</label>
                <input id="portal_subdomain" type="text" name="portal_subdomain" value="{{ old('portal_subdomain', $reseller->portal_subdomain) }}" class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="portal_custom_domain">Custom domain</label>
                <input id="portal_custom_domain" type="text" name="portal_custom_domain" value="{{ old('portal_custom_domain', $reseller->portal_custom_domain) }}" class="rsl-input" placeholder="portal.partner.com">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="portal_login_message">Login message</label>
                <textarea id="portal_login_message" name="portal_login_message" rows="3" class="rsl-input">{{ old('portal_login_message', $reseller->portal_login_message) }}</textarea>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="brand_logo">Logo</label>
                <input id="brand_logo" type="file" name="brand_logo" accept="image/*" class="rsl-input" style="padding:0.5rem">
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="white_label_enabled" value="1" class="rounded border-slate-300" @checked($reseller->white_label_enabled)>
                Enable white-label
            </label>
            <button type="submit" class="rsl-btn">Save branding</button>
        </form>
    </div>

    @if (! empty($links))
        <div class="rsl-panel rsl-panel-pad mt-6" style="max-width:36rem">
            <h2 class="rsl-panel-title">Share links</h2>
            <ul class="mt-3 space-y-2 font-mono text-xs break-all" style="color:var(--rsl-text-muted)">
                @foreach ($links as $label => $url)
                    @if (is_string($url) && $url !== '')
                        <li><span class="font-sans font-semibold" style="color:var(--rsl-text)">{{ $label }}:</span> {{ $url }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($sslGuide))
        <div class="rsl-panel rsl-panel-pad mt-6" style="max-width:36rem">
            <h2 class="rsl-panel-title">SSL setup</h2>
            <pre class="mt-3 whitespace-pre-wrap rounded-lg p-3 text-xs leading-relaxed rsl-code-block">{{ $sslGuide }}</pre>
        </div>
    @endif
@endsection
