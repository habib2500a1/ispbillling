@extends('reseller.layout')

@section('title', 'Payment settings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('reseller.settings.index') }}" class="text-sm font-semibold text-sky-700">← Integrations</a>
    </div>

    <div class="rsl-card p-6 max-w-xl">
        <h1 class="text-xl font-bold text-slate-900">Personal bKash / Nagad</h1>
        <p class="mt-1 text-sm text-slate-600">Send Money style — customers pay your number and enter TrxID.</p>

        <form method="post" action="{{ route('reseller.settings.payment.update') }}" class="mt-6 grid gap-6">
            @csrf
            @method('PUT')

            <fieldset class="grid gap-3 rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-800">bKash Personal</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="bkash_enabled" value="1" class="rounded border-slate-300" @checked($state['bkash_enabled'])>
                    Enable bKash Personal
                </label>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500">bKash number</label>
                    <input name="bkash_personal_number" value="{{ old('bkash_personal_number', $state['bkash_personal_number']) }}" placeholder="01XXXXXXXXX" class="mt-1 w-full rounded-lg border px-3 py-2 text-base">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500">Account name</label>
                    <input name="bkash_personal_name" value="{{ old('bkash_personal_name', $state['bkash_personal_name']) }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-base">
                </div>
            </fieldset>

            <fieldset class="grid gap-3 rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-800">Nagad Personal</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="nagad_enabled" value="1" class="rounded border-slate-300" @checked($state['nagad_enabled'])>
                    Enable Nagad Personal
                </label>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500">Nagad number</label>
                    <input name="nagad_personal_number" value="{{ old('nagad_personal_number', $state['nagad_personal_number']) }}" placeholder="01XXXXXXXXX" class="mt-1 w-full rounded-lg border px-3 py-2 text-base">
                </div>
            </fieldset>

            <fieldset class="grid gap-3 rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-800">SMS auto-verify (MFS app)</legend>
                <label class="flex items-center gap-2 text-sm font-semibold">
                    <input type="checkbox" name="mfs_ingest_enabled" value="1" class="rounded border-slate-300" @checked($state['mfs_ingest_enabled'])>
                    Enable SMS forwarder
                </label>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500">Device API key</label>
                    <input type="password" name="mfs_device_key" placeholder="{{ $state['mfs_device_key_set'] ? '•••••••• (leave blank to keep)' : 'Generate a strong random key' }}" class="mt-1 w-full rounded-lg border px-3 py-2 font-mono text-sm" autocomplete="new-password">
                </div>
                <p class="text-xs text-slate-500">
                    Ingest URL: <code class="rounded bg-slate-100 px-1">{{ $ingestUrl }}</code><br>
                    Header: <code class="rounded bg-slate-100 px-1">X-MFS-Device-Key</code> or <code class="rounded bg-slate-100 px-1">Authorization: Bearer …</code>
                </p>
            </fieldset>

            <button type="submit" class="rsl-btn w-full">Save payment settings</button>
        </form>
    </div>
@endsection
