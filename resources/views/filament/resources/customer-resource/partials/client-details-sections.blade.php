@props(['sections' => [], 'keys' => [], 'compact' => false])

@php
    $titles = [
        'account' => 'Account',
        'billing' => 'Billing & package',
        'connection' => 'Connection',
        'identity' => 'Identity',
        'location' => 'Location',
        'fees' => 'Fees & deposit',
        'installation' => 'Installation',
        'staff' => 'Staff assignment',
        'onu_billing' => 'ONU & device lease',
        'notifications' => 'Notifications',
        'automation' => 'Automation',
        'tags' => 'Tags',
        'kyc' => 'KYC',
        'system' => 'System',
        'legacy_meta' => 'Extra fields',
    ];

    $hiddenLabels = [
        'Import Source',
        'Legacy ID',
        'Database ID',
        'Tenant ID',
        'ISP Digital sync',
    ];

    $labelAliases = [
        'ISP Digital server' => 'Network server',
        'Connection (ISP Digital)' => 'Connection type',
        'Device (ISP Digital)' => 'CPE device',
    ];

    $isEmptyValue = static fn (mixed $value): bool => $value === '—' || $value === '' || $value === null;

    $shouldHideLabel = static function (string $label) use ($hiddenLabels): bool {
        if (in_array($label, $hiddenLabels, true)) {
            return true;
        }

        $lower = strtolower($label);

        return str_contains($lower, 'isp digital')
            || str_starts_with($lower, 'meta.isp_digital');
    };
@endphp

<div @class([
    'isp-cv-panels',
    'isp-cv-panels--compact' => $compact,
])>
    @foreach ($keys as $key)
        @php
            $fields = collect($sections[$key] ?? [])
                ->reject(fn ($value, $label): bool => $shouldHideLabel((string) $label))
                ->mapWithKeys(fn ($value, $label) => [
                    $labelAliases[$label] ?? $label => $value,
                ])
                ->reject(fn ($value): bool => $isEmptyValue($value))
                ->all();
        @endphp
        @if ($fields !== [])
            <section class="isp-cv-card">
                <h3 class="isp-cv-card__title">{{ $titles[$key] ?? str_replace('_', ' ', ucfirst($key)) }}</h3>
                <dl class="isp-cv-fields">
                    @foreach ($fields as $label => $value)
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
