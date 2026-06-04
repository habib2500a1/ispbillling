@extends('reseller.layout')

@section('title', 'Payment settings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="rsl-page-back">← Settings</a>
    </div>

    @include('reseller.partials.page-header', [
        'title' => 'Personal bKash / Nagad',
        'subtitle' => 'Send Money — customers pay your number and enter TrxID.',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:32rem">
        <form method="post" action="{{ route('reseller.settings.payment.update') }}" class="rsl-form-grid">
            @csrf
            @method('PUT')

            <fieldset class="rsl-form-fieldset">
                <legend class="rsl-form-legend">bKash Personal</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="bkash_enabled" value="1" class="rounded border-slate-300" @checked($state['bkash_enabled'])>
                    Enable bKash Personal
                </label>
                <div class="rsl-field">
                    <label class="rsl-field-label">bKash number</label>
                    <input name="bkash_personal_number" value="{{ old('bkash_personal_number', $state['bkash_personal_number']) }}" placeholder="01XXXXXXXXX" class="rsl-input">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label">Account name</label>
                    <input name="bkash_personal_name" value="{{ old('bkash_personal_name', $state['bkash_personal_name']) }}" class="rsl-input">
                </div>
            </fieldset>

            <fieldset class="rsl-form-fieldset">
                <legend class="rsl-form-legend">Nagad Personal</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="nagad_enabled" value="1" class="rounded border-slate-300" @checked($state['nagad_enabled'])>
                    Enable Nagad Personal
                </label>
                <div class="rsl-field">
                    <label class="rsl-field-label">Nagad number</label>
                    <input name="nagad_personal_number" value="{{ old('nagad_personal_number', $state['nagad_personal_number']) }}" placeholder="01XXXXXXXXX" class="rsl-input">
                </div>
            </fieldset>

            <fieldset class="rsl-form-fieldset">
                <legend class="rsl-form-legend">SMS auto-verify (MFS app)</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="mfs_ingest_enabled" value="1" class="rounded border-slate-300" @checked($state['mfs_ingest_enabled'])>
                    Enable SMS forwarder
                </label>
                <div class="rsl-field">
                    <label class="rsl-field-label">Device API key</label>
                    <input type="password" name="mfs_device_key" placeholder="{{ $state['mfs_device_key_set'] ? '•••••••• (leave blank to keep)' : 'Generate a strong random key' }}" class="rsl-input font-mono text-sm" autocomplete="new-password">
                </div>
                <p class="text-xs" style="color:var(--rsl-text-muted)">
                    Ingest URL: <code class="rsl-code-inline">{{ $ingestUrl }}</code><br>
                    Header: <code class="rsl-code-inline">X-MFS-Device-Key</code> or <code class="rsl-code-inline">Authorization: Bearer …</code>
                </p>
            </fieldset>

            <button type="submit" class="rsl-btn w-full">Save payment settings</button>
        </form>
    </div>
@endsection
