@extends('reseller.layout')

@section('title', 'SMS settings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="rsl-page-back">← Settings</a>
    </div>

    @include('reseller.partials.page-header', [
        'title' => 'SMS gateway',
        'subtitle' => 'Send SMS to your subscribers using your own gateway credentials.',
    ])

    <div class="rsl-panel rsl-panel-pad rsl-panel-narrow">
        <form method="post" action="{{ route('reseller.settings.sms.update') }}" class="rsl-stack">
            @csrf
            @method('PUT')

            <label class="rsl-field rsl-field-check">
                <input type="checkbox" name="sms_enabled" value="1" @checked($state['sms_enabled'])>
                <span>Enable SMS for my customers</span>
            </label>

            <div class="rsl-field">
                <label class="rsl-field-label" for="sms_provider">Provider</label>
                <select id="sms_provider" name="sms_provider" class="rsl-input">
                    @foreach (['khudebarta' => 'KhudeBarta', 'bulksmsbd' => 'BulkSMSBD', 'sslwireless' => 'SSL Wireless', 'custom' => 'Custom HTTP'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('sms_provider', $state['sms_provider']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('sms_provider')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label" for="sms_api_url">API URL</label>
                <input id="sms_api_url" type="url" name="sms_api_url" value="{{ old('sms_api_url', $state['sms_api_url']) }}" class="rsl-input" placeholder="https://">
                @error('sms_api_url')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label" for="sms_sender_id">Sender ID</label>
                <input id="sms_sender_id" name="sms_sender_id" value="{{ old('sms_sender_id', $state['sms_sender_id']) }}" maxlength="32" class="rsl-input" placeholder="YourBrand">
                <p class="rsl-field-hint">Shown on outbound SMS. Must match your provider’s approved sender ID.</p>
                @error('sms_sender_id')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label" for="sms_api_key">API key</label>
                <input id="sms_api_key" type="password" name="sms_api_key" class="rsl-input" autocomplete="new-password"
                    placeholder="{{ $state['sms_api_key_set'] ? 'Leave blank to keep current key' : 'Enter API key' }}">
                @error('sms_api_key')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
            </div>

            <div class="rsl-field">
                <label class="rsl-field-label" for="sms_secret_key">Secret key</label>
                <input id="sms_secret_key" type="password" name="sms_secret_key" class="rsl-input" autocomplete="new-password"
                    placeholder="{{ $state['sms_secret_key_set'] ? 'Leave blank to keep current secret' : 'Enter secret key' }}">
                @error('sms_secret_key')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="rsl-btn">Save SMS settings</button>
        </form>
    </div>
@endsection
