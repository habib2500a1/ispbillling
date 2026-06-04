@extends('reseller.layout')

@section('title', 'White-label branding')

@section('content')
    <div class="rsl-card p-6 max-w-2xl">
        <h1 class="rsl-title">White-label branding</h1>
        <form method="post" action="{{ route('reseller.branding.update') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-sm rsl-text-muted">Brand name</label>
                <input type="text" name="brand_name" value="{{ old('brand_name', $reseller->brand_name) }}" class="rsl-input mt-1 w-full">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm rsl-text-muted">Primary color</label>
                    <input type="text" name="brand_primary_color" value="{{ old('brand_primary_color', $reseller->brand_primary_color) }}" class="rsl-input mt-1 w-full" placeholder="#2563eb">
                </div>
                <div>
                    <label class="text-sm rsl-text-muted">Secondary color</label>
                    <input type="text" name="brand_secondary_color" value="{{ old('brand_secondary_color', $reseller->brand_secondary_color) }}" class="rsl-input mt-1 w-full">
                </div>
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Portal subdomain</label>
                <input type="text" name="portal_subdomain" value="{{ old('portal_subdomain', $reseller->portal_subdomain) }}" class="rsl-input mt-1 w-full">
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Custom domain</label>
                <input type="text" name="portal_custom_domain" value="{{ old('portal_custom_domain', $reseller->portal_custom_domain) }}" class="rsl-input mt-1 w-full" placeholder="portal.partner.com">
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Login page message</label>
                <textarea name="portal_login_message" rows="3" class="rsl-input mt-1 w-full">{{ old('portal_login_message', $reseller->portal_login_message) }}</textarea>
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Logo</label>
                <input type="file" name="brand_logo" accept="image/*" class="mt-1">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="white_label_enabled" value="1" @checked($reseller->white_label_enabled)> Enable white-label
            </label>
            <button type="submit" class="rsl-btn-sm">Save branding</button>
        </form>
    </div>
@endsection
