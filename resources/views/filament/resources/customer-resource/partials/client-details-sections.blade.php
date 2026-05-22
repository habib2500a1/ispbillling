@props(['sections' => [], 'keys' => [], 'compact' => false])

<div @class([
    'isp-cv-panels',
    'isp-cv-panels--compact' => $compact,
])>
    @foreach ($keys as $key)
        @php $fields = $sections[$key] ?? []; @endphp
        @if ($fields !== [])
            <section class="isp-cv-card">
                <h3 class="isp-cv-card__title">{{ str_replace('_', ' ', ucfirst($key)) }}</h3>
                <dl class="isp-cv-fields">
                    @foreach ($fields as $label => $value)
                        @continue($value === '—' || $value === '' || $value === null)
                        <div class="isp-cv-field">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    @endforeach
</div>
