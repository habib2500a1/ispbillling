@extends('reseller.layout')

@section('title', 'SMS settings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="text-sm font-semibold text-sky-700">← Integrations</a>
    </div>

    <div class="rsl-card p-6 max-w-xl">
        <h1 class="text-xl font-bold text-slate-900">SMS gateway</h1>
        <p class="mt-1 text-sm text-slate-600">Customer SMS for your subscribers only.</p>

        <form method="post" action="{{ route('reseller.settings.sms.update') }}" class="mt-6 grid gap-4">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="sms_enabled" value="1" class="rounded border-slate-300" @checked($state['sms_enabled'])>
                Enable SMS for my customers
            </label>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Provider</label>
                <select name="sms_provider" class="mt-1 w-full rounded-lg border px-3 py-2 text-base">
                    @foreach (['khudebarta' => 'KhudeBarta', 'bulksmsbd' => 'BulkSMSBD', 'sslwireless' => 'SSL Wireless', 'custom' => 'Custom HTTP'] as $value => $label)
                        <option value="{{ $value }}" @selected($state['sms_provider'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">API URL</label>
                <input type="url" name="sms_api_url" value="{{ old('sms_api_url', $state['sms_api_url']) }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-base">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Sender ID</label>
                <input name="sms_sender_id" value="{{ old('sms_sender_id', $state['sms_sender_id']) }}" maxlength="32" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" placeholder="YourBrand">
                <p class="mt-1 text-xs text-slate-500">Name shown on SMS to your customers. Must match your gateway account (e.g. KhudeBarta approved sender).</p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">API key</label>
                <input type="password" name="sms_api_key" placeholder="{{ $state['sms_api_key_set'] ? '•••••••• (leave blank to keep)' : 'Enter API key' }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" autocomplete="new-password">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Secret key</label>
                <input type="password" name="sms_secret_key" placeholder="{{ $state['sms_secret_key_set'] ? '•••••••• (leave blank to keep)' : 'Enter secret key' }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-base" autocomplete="new-password">
            </div>

            <button type="submit" class="rsl-btn w-full">Save SMS settings</button>
        </form>
    </div>
@endsection
