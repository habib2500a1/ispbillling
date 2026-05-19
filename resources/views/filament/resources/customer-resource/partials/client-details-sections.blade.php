@props(['sections' => [], 'keys' => []])

<div class="isp-client-details__panels isp-client-details__panels--full">
    @foreach ($keys as $key)
        @php $fields = $sections[$key] ?? []; @endphp
        @if ($fields !== [])
            <section class="isp-cd-panel">
                <h2 class="isp-cd-panel__heading">{{ str_replace('_', ' ', ucfirst($key)) }}</h2>
                <dl class="isp-cd-fields">
                    @foreach ($fields as $label => $value)
                        <div class="isp-cd-field">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif
    @endforeach
</div>
