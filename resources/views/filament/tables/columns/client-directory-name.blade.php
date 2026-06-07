@php
    /** @var \App\Models\Customer $record */
    $record = $getRecord();
    $viewUrl = \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $record]);
    $editUrl = \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $record]);
    $parts = preg_split('/\s+/', trim((string) $record->name)) ?: [];
    $initials = collect($parts)
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';
    $pppUser = filled($record->mikrotik_secret_name)
        ? (string) $record->mikrotik_secret_name
        : (filled($record->radius_username) ? (string) $record->radius_username : null);
    $onuOwnership = \App\Support\OnuOwnership::forCustomer($record);
    $onuLabel = \App\Support\OnuOwnership::label($onuOwnership);
    $onuTone = \App\Support\OnuOwnership::badgeTone($onuOwnership);
    $meta = is_array($record->meta) ? $record->meta : [];
    $hasGps = filled($meta['gps_lat'] ?? null) && filled($meta['gps_lng'] ?? null);
    $isOnline = $record->isPppOnline();
    $statusLabel = $record->statusLabel();
    $statusTone = match ((string) $record->status) {
        'active' => 'success',
        'suspended', 'left' => 'danger',
        'pending', 'inactive' => 'warning',
        default => 'muted',
    };
@endphp

<div class="cl-dir-client">
    <span @class([
        'cl-dir-client__avatar',
        'cl-dir-client__avatar--online' => $isOnline,
        'cl-dir-client__avatar--offline' => ! $isOnline,
    ]) aria-hidden="true">
        <span class="cl-dir-client__initials">{{ $initials }}</span>
        <span @class([
            'cl-dir-client__presence',
            'cl-dir-client__presence--online' => $isOnline,
            'cl-dir-client__presence--offline' => ! $isOnline,
        ])></span>
    </span>

    <div class="cl-dir-client__body">
        <div class="cl-dir-client__title-row">
            <a href="{{ $viewUrl }}" class="cl-dir-client__name" title="{{ $record->name }}">{{ $record->name }}</a>
            @if ($hasGps)
                <a
                    href="https://www.google.com/maps?q={{ rawurlencode($meta['gps_lat'].','.$meta['gps_lng']) }}"
                    class="cl-dir-client__gps"
                    target="_blank"
                    rel="noopener"
                    title="Open saved GPS pin"
                    aria-label="GPS saved — open map"
                >
                    <x-filament::icon icon="heroicon-m-map-pin" class="h-3.5 w-3.5" />
                </a>
            @endif
        </div>

        <div class="cl-dir-client__meta-row">
            @if ($pppUser)
                <span class="cl-dir-client__ppp" title="PPPoE username">{{ $pppUser }}</span>
            @endif
            <span class="cl-dir-onu-badge cl-dir-onu-badge--{{ $onuTone }}">{{ $onuLabel }}</span>
            <span class="cl-dir-client__status cl-dir-client__status--{{ $statusTone }}">{{ $statusLabel }}</span>
        </div>

        @if (filled($record->phone))
            <a href="tel:{{ preg_replace('/\D+/', '', (string) $record->phone) }}" class="cl-dir-client__phone">{{ $record->phone }}</a>
        @endif
    </div>

    <div class="cl-dir-client__actions">
        <a href="{{ $viewUrl }}" class="cl-dir-client__action" title="360° view" aria-label="Open client view">
            <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4" />
        </a>
        <a href="{{ $editUrl }}" class="cl-dir-client__action" title="Edit client" aria-label="Edit client">
            <x-filament::icon icon="heroicon-m-pencil-square" class="h-4 w-4" />
        </a>
    </div>
</div>
