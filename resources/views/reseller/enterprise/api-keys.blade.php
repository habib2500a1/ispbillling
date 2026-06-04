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
        'subtitle' => 'For mobile apps and integrations.',
    ])

    <div class="rsl-panel rsl-panel-pad">
        @if (! $reseller->api_access_enabled)
            <div class="rsl-callout rsl-callout--info">
                API access disabled. Ask admin to enable.
            </div>
        @else
            <form method="post" action="{{ route('reseller.api-keys.store') }}" class="rsl-form-grid rsl-form-grid--2" style="max-width:28rem">
                @csrf
                <div class="rsl-field" style="grid-column:1/-1">
                    <label class="rsl-field-label" for="key_name">Key name</label>
                    <input id="key_name" type="text" name="name" required placeholder="e.g. Mobile app" class="rsl-input">
                </div>
                <div>
                    <button type="submit" class="rsl-btn">Create key</button>
                </div>
            </form>
        @endif

        <div class="rsl-table-wrap mt-6">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Prefix</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($keys as $key)
                        <tr>
                            <td class="px-4 py-3">{{ $key->name }}</td>
                            <td class="px-4 py-3 font-mono">{{ $key->key_prefix }}…</td>
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
                            <td colspan="4" class="px-4 py-8 text-center" style="color:var(--rsl-text-muted)">No keys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
