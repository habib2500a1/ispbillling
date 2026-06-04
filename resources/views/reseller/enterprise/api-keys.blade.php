@extends('reseller.layout')

@section('title', 'API keys')

@section('content')
    @if (session('new_api_key'))
        <div class="rsl-alert rsl-alert--success mb-4">
            <p class="font-semibold">New API key (copy now):</p>
            <code class="mt-2 block break-all text-xs">{{ session('new_api_key') }}</code>
        </div>
    @endif

    @include('reseller.partials.page-header', [
        'title' => 'API keys',
        'subtitle' => 'Integrations use read-only GET /api/v1/reseller/* (or legacy /partner/*) with Bearer rsk_… keys. Writes need a Sanctum token.',
    ])

    <div class="rsl-panel rsl-panel-pad">
        @if (! $reseller->api_access_enabled)
            <div class="rsl-callout rsl-callout--info">
                API access disabled. Ask admin to enable.
            </div>
        @else
            <form method="post" action="{{ route('reseller.api-keys.store') }}" class="rsl-stack" style="max-width:42rem">
                @csrf
                <div class="rsl-field">
                    <label class="rsl-field-label" for="key_name">Key name</label>
                    <input id="key_name" type="text" name="name" required placeholder="e.g. Mobile app" class="rsl-input" value="{{ old('name') }}">
                    @error('name')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
                </div>

                @if (count($apiKeyPermissionOptions) > 0)
                    <div class="rsl-field">
                        <span class="rsl-field-label">API scope (optional)</span>
                        <p class="rsl-field-hint mb-3">Leave all unchecked for full read-only partner API access. Select permissions to restrict this key.</p>
                        <div class="rsl-form-grid rsl-form-grid--2">
                            @foreach ($apiKeyPermissionOptions as $key => $label)
                                <label class="rsl-settings-tile" style="padding:0.75rem 1rem;cursor:pointer">
                                    <span class="flex items-start gap-2 text-sm" style="display:flex;gap:0.5rem">
                                        <input type="checkbox" name="abilities[]" value="{{ $key }}" style="margin-top:0.2rem"
                                            @checked(in_array($key, old('abilities', []), true))>
                                        <span>{{ $label }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('abilities')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
                        @error('abilities.*')<p class="rsl-field-hint" style="color:var(--rsl-danger)">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div>
                    <button type="submit" class="rsl-btn">Create key</button>
                </div>
            </form>
        @endif

        <div class="rsl-table-wrap mt-8">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Prefix</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Last used</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $allLabels = \App\Support\ResellerPortalPermission::labels();
                    @endphp
                    @forelse ($keys as $key)
                        @php
                            $abilities = $key->abilities;
                            $scopeLabel = ! is_array($abilities) || $abilities === []
                                ? 'Full access'
                                : collect($abilities)->map(fn ($p) => $allLabels[$p] ?? $p)->join(', ');
                        @endphp
                        <tr>
                            <td class="px-4 py-3">{{ $key->name }}</td>
                            <td class="px-4 py-3 font-mono">{{ $key->key_prefix }}…</td>
                            <td class="px-4 py-3" style="max-width:14rem;color:var(--rsl-text-muted)">{{ $scopeLabel }}</td>
                            <td class="px-4 py-3" style="color:var(--rsl-text-muted)">
                                {{ $key->last_used_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rsl-badge-pill {{ $key->is_active ? 'rsl-badge-pill--ok' : 'rsl-badge-pill--muted' }}">
                                    {{ $key->is_active ? 'Active' : 'Revoked' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($key->is_active)
                                    <form method="post" action="{{ route('reseller.api-keys.destroy', $key) }}" class="inline" onsubmit="return confirm('Revoke this key?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rsl-link-action" style="color:var(--rsl-danger)">Revoke</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center" style="color:var(--rsl-text-muted)">No keys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usageLogs->isNotEmpty())
            <h3 class="rsl-section-title mt-8 mb-3">Recent API usage</h3>
            <div class="rsl-table-wrap">
                <table class="rsl-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Path</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usageLogs as $log)
                            <tr>
                                <td class="px-4 py-3" style="color:var(--rsl-text-muted)">{{ $log->created_at?->format('M j, H:i') }}</td>
                                <td class="px-4 py-3 font-mono">{{ $log->method }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $log->path }}</td>
                                <td class="px-4 py-3">{{ $log->status_code }}</td>
                                <td class="px-4 py-3">{{ $log->duration_ms }} ms</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
