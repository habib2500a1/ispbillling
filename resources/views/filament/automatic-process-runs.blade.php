<div class="max-h-96 overflow-y-auto">
    @if ($runs->isEmpty())
        <p class="text-sm text-gray-500">No runs recorded yet.</p>
    @else
        <table class="w-full text-left text-sm">
            <thead class="border-b text-xs uppercase text-gray-500">
                <tr>
                    <th class="py-2 pr-2">Started</th>
                    <th class="py-2 pr-2">Trigger</th>
                    <th class="py-2 pr-2">Status</th>
                    <th class="py-2">Duration</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $run)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-2 pr-2 whitespace-nowrap">{{ $run->started_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="py-2 pr-2">{{ $run->triggered_by }}</td>
                        <td class="py-2 pr-2">
                            <span @class([
                                'rounded px-1.5 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $run->status === 'success',
                                'bg-rose-100 text-rose-800' => $run->status === 'failed',
                                'bg-amber-100 text-amber-800' => $run->status === 'running',
                                'bg-gray-100 text-gray-700' => ! in_array($run->status, ['success', 'failed', 'running'], true),
                            ])>{{ $run->status }}</span>
                        </td>
                        <td class="py-2">
                            @if ($run->durationSeconds() !== null)
                                {{ $run->durationSeconds() }}s
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @if ($run->output)
                        <tr>
                            <td colspan="4" class="pb-3">
                                <pre class="max-h-24 overflow-auto rounded bg-gray-50 p-2 text-xs dark:bg-gray-900">{{ $run->output }}</pre>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>
