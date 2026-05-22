@props(['methods' => [], 'compact' => false])

@if (count($methods) > 0)
    <div @class(['bp-pay-methods', 'bp-pay-methods--compact' => $compact]) role="group" aria-label="Payment methods">
        @foreach ($methods as $method)
            <button
                type="submit"
                name="gateway"
                value="{{ $method['gateway'] }}"
                title="{{ $method['hint'] ?? '' }}"
                @class(['bp-pay-method', 'bp-pay-method--'.$method['tone']])
            >
                <span class="bp-pay-method__brand">{{ $method['label'] }}</span>
                <span @class([
                    'bp-pay-method__badge',
                    'bp-pay-method__badge--personal' => ($method['mode'] ?? '') === 'personal',
                    'bp-pay-method__badge--merchant' => ($method['mode'] ?? '') === 'merchant',
                ])>{{ $method['badge'] }}</span>
                @if (! $compact && filled($method['hint'] ?? ''))
                    <span class="bp-pay-method__hint">{{ $method['hint'] }}</span>
                @endif
            </button>
        @endforeach
    </div>
@endif
