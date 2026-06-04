@extends('reseller.layout')

@section('title', 'New partner')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'New sub-partner',
        'subtitle' => 'Create a partner account under your hierarchy.',
        'backUrl' => route('reseller.sub-resellers.index'),
        'backLabel' => '← Partners',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:36rem">
        <form method="post" action="{{ route('reseller.sub-resellers.store') }}" class="rsl-form-grid">
            @csrf
            <div class="rsl-field">
                <label class="rsl-field-label" for="name">Name</label>
                <input id="name" type="text" name="name" required class="rsl-input" value="{{ old('name') }}">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="franchise_type">Type</label>
                <select id="franchise_type" name="franchise_type" class="rsl-input">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('franchise_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rsl-form-grid rsl-form-grid--2">
                <div class="rsl-field">
                    <label class="rsl-field-label" for="portal_login">Portal login</label>
                    <input id="portal_login" type="text" name="portal_login" required class="rsl-input" value="{{ old('portal_login') }}">
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="portal_password">Password</label>
                    <input id="portal_password" type="password" name="portal_password" required class="rsl-input" autocomplete="new-password">
                </div>
            </div>
            <div class="rsl-form-grid rsl-form-grid--2">
                <div class="rsl-field">
                    <label class="rsl-field-label" for="commission_type">Commission Type</label>
                    <select id="commission_type" name="commission_type" class="rsl-input">
                        <option value="percent">Percent</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>
                <div class="rsl-field">
                    <label class="rsl-field-label" for="commission_value">Commission value</label>
                    <input id="commission_value" type="number" name="commission_value" step="0.01" min="0" required class="rsl-input" value="{{ old('commission_value', 10) }}">
                </div>
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="max_clients">Max subscribers (optional)</label>
                <input id="max_clients" type="number" name="max_clients" min="0" class="rsl-input" value="{{ old('max_clients') }}">
            </div>
            <button type="submit" class="rsl-btn">Create partner</button>
        </form>
    </div>
@endsection
