<div class="space-y-2 text-sm">
    <p><strong>Event:</strong> {{ $record->event }}</p>
    <p><strong>Description:</strong> {{ $record->description ?? '—' }}</p>
    <p><strong>User:</strong> {{ $record->user?->email ?? '—' }}</p>
    <p><strong>IP:</strong> {{ $record->ip_address ?? '—' }}</p>
    <p><strong>Time:</strong> {{ $record->created_at?->toDateTimeString() }}</p>
    @if($record->properties)
        <pre class="mt-2 max-h-48 overflow-auto rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">{{ json_encode($record->properties, JSON_PRETTY_PRINT) }}</pre>
    @endif
</div>
