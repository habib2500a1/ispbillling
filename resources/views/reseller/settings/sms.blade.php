@extends('reseller.layout')

@section('title', 'SMS settings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="rsl-page-back">← Settings</a>
    </div>

    @include('reseller.partials.page-header', [
        'title' => 'SMS gateway',
        'subtitle' => 'Customer SMS for your subscribers only.',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:32rem">
        <form method="post" action="{{ route('reseller.settings.sms.update') }}" class="rsl-form-grid">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="sms_enabled" value="1" class="rounded border-slate-300" @checked($state['sms_enabled'])>
                Enable SMS for my customers
            </label>

            <div>
                <label class="rsl-field-label">Provider</label>
                <select name="sms_provider" class="rsl-input text-base">
                    @foreach (['khudebarta' => 'KhudeBarta', 'bulksmsbd' => 'BulkSMSBD', 'sslwireless' => 'SSL Wireless', 'custom' => 'Custom HTTP'] as $value => $label)
                        <option value="{{ $value }}" @selected($state['sms_provider'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="rsl-field-label">API URL</label>
                <input type="url" name="sms_api_url" value="{{ old('sms_api_url', $state['sms_api_url']) }}" class="rsl-input text-base">
            </div>

            <div>
                <label class="rsl-field-label">Sender ID</label>
                <input name="sms_sender_id" value="{{ old('sms_sender_id', $state['sms_sender_id']) }}" maxlength="32" class="rsl-input text-base" placeholder="YourBrand">
                <p class="mt-1 text-xs text-slate-500">Name shown on SMS to your customers. Must match your gateway account (e.g. KhudeBarta approved sender).</p>
            </div>

            <div>
                <label class="rsl-field-label">API key</label>
                <input type="password" name="sms_api_key" placeholder="{{ $state['sms_api_key_set'] ? '•••••••• (leave blank to keep)' : 'Enter API key' }}" class="rsl-input text-base" autocomplete="new-password">
            </div>

            <div>
                <label class="rsl-field-label">Secret key</label>
                <input type="password" name="sms_secret_key" placeholder="{{ $state['sms_secret_key_set'] ? '•••••••• (leave blank to keep)' : 'Enter secret key' }}" class="rsl-input text-base" autocomplete="new-password">
            </div>

            <button type="submit" class="rsl-btn w-full">Save SMS settings</button>
        </form>
    </div>
@endsection
