@php
    /** @var \App\Models\Customer $record */
    $record = $getRecord();
    $parts = preg_split('/\s+/', trim((string) $record->name)) ?: [];
    $initials = collect($parts)
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $initials = $initials !== '' ? $initials : '?';
@endphp

<div class="cl-dir-client">
    <span class="cl-dir-client__avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="cl-dir-client__body">
        <span class="cl-dir-client__name">{{ $record->name }}</span>
        @if (filled($record->phone))
            <span class="cl-dir-client__phone">{{ $record->phone }}</span>
        @endif
    </div>
</div>
