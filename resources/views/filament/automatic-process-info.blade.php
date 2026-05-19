<div class="space-y-3 text-sm">
    <div>
        <span class="font-medium text-gray-700 dark:text-gray-300">Command</span>
        <p class="mt-0.5 font-mono text-xs">{{ $record->artisan_command }}</p>
    </div>
    @if ($record->command_options)
        <div>
            <span class="font-medium text-gray-700 dark:text-gray-300">Options</span>
            <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-50 p-2 text-xs dark:bg-gray-900">{{ json_encode($record->command_options, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
    <dl class="grid grid-cols-2 gap-2">
        <dt class="text-gray-500">Next run ({{ config('isp.timezone_label', 'BDT') }})</dt>
        <dd>{{ $record->next_run_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</dd>
        <dt class="text-gray-500">Last run ({{ config('isp.timezone_label', 'BDT') }})</dt>
        <dd>{{ $record->last_run_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</dd>
        <dt class="text-gray-500">Status</dt>
        <dd>{{ $record->last_status ?? '—' }}</dd>
    </dl>
    @if ($record->last_output)
        <div>
            <span class="font-medium text-gray-700 dark:text-gray-300">Last output</span>
            <pre class="mt-1 max-h-48 overflow-auto rounded-lg bg-gray-50 p-2 text-xs dark:bg-gray-900">{{ $record->last_output }}</pre>
        </div>
    @endif
</div>
