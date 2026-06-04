@extends('reseller.layout')

@section('title', 'Create sub-reseller')

@section('content')
    <div class="rsl-card p-6 max-w-xl">
        <h1 class="rsl-title">Create sub-reseller</h1>
        <form method="post" action="{{ route('reseller.sub-resellers.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="text-sm rsl-text-muted">Name</label>
                <input type="text" name="name" required class="rsl-input mt-1 w-full" value="{{ old('name') }}">
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Type</label>
                <select name="franchise_type" class="rsl-input mt-1 w-full">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('franchise_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm rsl-text-muted">Portal login</label>
                    <input type="text" name="portal_login" required class="rsl-input mt-1 w-full" value="{{ old('portal_login') }}">
                </div>
                <div>
                    <label class="text-sm rsl-text-muted">Password</label>
                    <input type="password" name="portal_password" required class="rsl-input mt-1 w-full">
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm rsl-text-muted">Commission type</label>
                    <select name="commission_type" class="rsl-input mt-1 w-full">
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm rsl-text-muted">Commission value</label>
                    <input type="number" name="commission_value" step="0.01" min="0" required class="rsl-input mt-1 w-full" value="{{ old('commission_value', 10) }}">
                </div>
            </div>
            <div>
                <label class="text-sm rsl-text-muted">Max customers (optional)</label>
                <input type="number" name="max_clients" min="0" class="rsl-input mt-1 w-full" value="{{ old('max_clients') }}">
            </div>
            <button type="submit" class="rsl-btn-sm">Create partner</button>
        </form>
    </div>
@endsection
