@extends('reseller.layout')

@section('title', 'API keys')

@section('content')
    @if (session('new_api_key'))
        <div class="rsl-card p-4 mb-4 border border-emerald-300 bg-emerald-50">
            <p class="text-sm font-semibold text-emerald-900">New API key (copy now):</p>
            <code class="mt-2 block break-all text-xs">{{ session('new_api_key') }}</code>
        </div>
    @endif

    <div class="rsl-card p-6">
        <h1 class="rsl-title">API keys</h1>
        @if (! $reseller->api_access_enabled)
            <p class="mt-2 text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm">API access is disabled. Contact admin to enable.</p>
        @else
            <form method="post" action="{{ route('reseller.api-keys.store') }}" class="mt-4 flex gap-2">
                @csrf
                <input type="text" name="name" required placeholder="Key name" class="rsl-input flex-1">
                <button type="submit" class="rsl-btn-sm">Create key</button>
            </form>
        @endif
        <ul class="mt-6 space-y-2 text-sm">
            @foreach ($keys as $key)
                <li class="flex items-center justify-between border-b py-2">
                    <span>{{ $key->name }} · {{ $key->key_prefix }}… · {{ $key->is_active ? 'Active' : 'Revoked' }}</span>
                    @if ($key->is_active)
                        <form method="post" action="{{ route('reseller.api-keys.destroy', $key) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-rose-600 text-xs">Revoke</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endsection
