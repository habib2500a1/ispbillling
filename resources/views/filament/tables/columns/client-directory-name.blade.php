@php
    /** @var \App\Models\Customer $record */
    $record = $getRecord();
    $viewUrl = \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $record]);
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
@endphp

<div class="cl-dir-client">
    <span class="cl-dir-client__avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="cl-dir-client__body">
        <a href="{{ $viewUrl }}" class="cl-dir-client__name">{{ $record->name }}</a>
        @if ($pppUser)
            <span class="cl-dir-client__ppp">{{ $pppUser }}</span>
        @endif
    </div>
</div>
