@props([
    'label',
    'name' => null,
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'class' => '',
])

<div class="rsl-field {{ $class }}">
    <label class="rsl-field-label" @if($name) for="{{ $name }}" @endif>
        {{ $label }}
        @if ($required)<span class="rsl-field-req">*</span>@endif
    </label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif
    @if ($hint)
        <p class="rsl-field-hint">{{ $hint }}</p>
    @endif
</div>
